# =============================================================================
# ProcuChain MultiChain GCP Infrastructure - Main Configuration
# =============================================================================
# This Terraform configuration deploys a 5-node MultiChain network on GCP:
# - 1 Admin/Seed Node (creates and administers the blockchain)
# - 2 Application Nodes (handle web app RPC requests, load balanced)
# - 1 Witness Node (independent validator in different zone)
# - 1 Backup Node (disaster recovery standby)
# =============================================================================

terraform {
  required_version = ">= 1.5.0"

  required_providers {
    google = {
      source  = "hashicorp/google"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.5"
    }
  }

  # Uncomment and configure for remote state storage
  # backend "gcs" {
  #   bucket = "procuchain-terraform-state"
  #   prefix = "multichain/state"
  # }
}

provider "google" {
  project = var.project_id
  region  = var.region
}

# -----------------------------------------------------------------------------
# Random password for RPC authentication
# -----------------------------------------------------------------------------

resource "random_password" "rpc_password" {
  length  = 32
  special = false # MultiChain has issues with some special chars
}

# -----------------------------------------------------------------------------
# Secret Manager for RPC credentials
# -----------------------------------------------------------------------------

resource "google_secret_manager_secret" "rpc_password" {
  secret_id = "procuchain-rpc-password"

  labels = var.labels

  replication {
    auto {}
  }
}

resource "google_secret_manager_secret_version" "rpc_password" {
  secret      = google_secret_manager_secret.rpc_password.id
  secret_data = random_password.rpc_password.result
}

# -----------------------------------------------------------------------------
# Service Account for MultiChain nodes
# -----------------------------------------------------------------------------

resource "google_service_account" "multichain_node" {
  account_id   = "procuchain-multichain-node"
  display_name = "ProcuChain MultiChain Node Service Account"
  description  = "Service account for MultiChain blockchain nodes"
}

# Grant Secret Manager access
resource "google_secret_manager_secret_iam_member" "node_secret_access" {
  secret_id = google_secret_manager_secret.rpc_password.id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.multichain_node.email}"
}

# Grant Cloud Storage access for backups
resource "google_storage_bucket_iam_member" "node_backup_access" {
  count  = var.backup_bucket_name != "" ? 1 : 0
  bucket = var.backup_bucket_name
  role   = "roles/storage.objectAdmin"
  member = "serviceAccount:${google_service_account.multichain_node.email}"
}

# Grant logging and monitoring
resource "google_project_iam_member" "node_logging" {
  project = var.project_id
  role    = "roles/logging.logWriter"
  member  = "serviceAccount:${google_service_account.multichain_node.email}"
}

resource "google_project_iam_member" "node_monitoring" {
  project = var.project_id
  role    = "roles/monitoring.metricWriter"
  member  = "serviceAccount:${google_service_account.multichain_node.email}"
}

# -----------------------------------------------------------------------------
# VPC Network
# -----------------------------------------------------------------------------

resource "google_compute_network" "multichain" {
  name                    = var.vpc_name
  auto_create_subnetworks = false
  description             = "VPC network for ProcuChain MultiChain nodes"
}

resource "google_compute_subnetwork" "multichain" {
  name                     = "${var.vpc_name}-subnet"
  ip_cidr_range            = var.subnet_cidr
  region                   = var.region
  network                  = google_compute_network.multichain.id
  private_ip_google_access = true

  log_config {
    aggregation_interval = "INTERVAL_5_SEC"
    flow_sampling        = 0.5
    metadata             = "INCLUDE_ALL_METADATA"
  }
}

# Secondary subnet in backup region for DR
resource "google_compute_subnetwork" "multichain_backup" {
  name                     = "${var.vpc_name}-subnet-backup"
  ip_cidr_range            = "10.0.2.0/24"
  region                   = var.backup_region
  network                  = google_compute_network.multichain.id
  private_ip_google_access = true
}

# -----------------------------------------------------------------------------
# Firewall Rules
# -----------------------------------------------------------------------------

# Allow internal communication between nodes (P2P and RPC)
resource "google_compute_firewall" "internal" {
  name    = "${var.vpc_name}-allow-internal"
  network = google_compute_network.multichain.name

  allow {
    protocol = "tcp"
    ports    = [var.rpc_port, var.p2p_port]
  }

  allow {
    protocol = "icmp"
  }

  source_ranges = [var.subnet_cidr, "10.0.2.0/24"]
  target_tags   = ["multichain-node"]

  description = "Allow internal MultiChain traffic between nodes"
}

# Allow SSH from specified ranges (e.g., bastion host or office IP)
resource "google_compute_firewall" "ssh" {
  count   = length(var.allowed_ssh_ranges) > 0 ? 1 : 0
  name    = "${var.vpc_name}-allow-ssh"
  network = google_compute_network.multichain.name

  allow {
    protocol = "tcp"
    ports    = ["22"]
  }

  source_ranges = var.allowed_ssh_ranges
  target_tags   = ["multichain-node"]

  description = "Allow SSH access from authorized ranges"
}

# Allow RPC from application servers (for load balancer health checks too)
resource "google_compute_firewall" "rpc_app_servers" {
  count   = length(var.app_server_ips) > 0 ? 1 : 0
  name    = "${var.vpc_name}-allow-rpc-app"
  network = google_compute_network.multichain.name

  allow {
    protocol = "tcp"
    ports    = [var.rpc_port]
  }

  source_ranges = var.app_server_ips
  target_tags   = ["multichain-app-node"]

  description = "Allow RPC access from application servers"
}

# Allow health checks from GCP load balancer
resource "google_compute_firewall" "health_check" {
  name    = "${var.vpc_name}-allow-health-check"
  network = google_compute_network.multichain.name

  allow {
    protocol = "tcp"
    ports    = [var.rpc_port]
  }

  # GCP health check IP ranges
  source_ranges = ["130.211.0.0/22", "35.191.0.0/16"]
  target_tags   = ["multichain-app-node"]

  description = "Allow GCP health checks for load balancer"
}

# Allow IAP for SSH tunneling (secure SSH without public IP)
resource "google_compute_firewall" "iap_ssh" {
  name    = "${var.vpc_name}-allow-iap-ssh"
  network = google_compute_network.multichain.name

  allow {
    protocol = "tcp"
    ports    = ["22"]
  }

  # IAP IP range
  source_ranges = ["35.235.240.0/20"]
  target_tags   = ["multichain-node"]

  description = "Allow SSH via Identity-Aware Proxy"
}

# -----------------------------------------------------------------------------
# Cloud NAT (for nodes without public IPs to access internet)
# -----------------------------------------------------------------------------

resource "google_compute_router" "multichain" {
  name    = "${var.vpc_name}-router"
  region  = var.region
  network = google_compute_network.multichain.id
}

resource "google_compute_router_nat" "multichain" {
  name                               = "${var.vpc_name}-nat"
  router                             = google_compute_router.multichain.name
  region                             = var.region
  nat_ip_allocate_option             = "AUTO_ONLY"
  source_subnetwork_ip_ranges_to_nat = "ALL_SUBNETWORKS_ALL_IP_RANGES"

  log_config {
    enable = true
    filter = "ERRORS_ONLY"
  }
}

# -----------------------------------------------------------------------------
# Persistent Disks for Blockchain Data
# -----------------------------------------------------------------------------

resource "google_compute_disk" "admin_data" {
  name = "procuchain-admin-data"
  type = "pd-ssd"
  zone = var.zone
  size = var.data_disk_size_gb

  labels = merge(var.labels, {
    node_role = "admin"
  })
}

resource "google_compute_disk" "app_primary_data" {
  name = "procuchain-app-primary-data"
  type = "pd-ssd"
  zone = var.zone
  size = var.data_disk_size_gb

  labels = merge(var.labels, {
    node_role = "app-primary"
  })
}

resource "google_compute_disk" "app_secondary_data" {
  name = "procuchain-app-secondary-data"
  type = "pd-ssd"
  zone = var.zone
  size = var.data_disk_size_gb

  labels = merge(var.labels, {
    node_role = "app-secondary"
  })
}

resource "google_compute_disk" "witness_data" {
  name = "procuchain-witness-data"
  type = "pd-ssd"
  zone = var.secondary_zone
  size = var.witness_data_disk_size_gb

  labels = merge(var.labels, {
    node_role = "witness"
  })
}

resource "google_compute_disk" "backup_data" {
  name     = "procuchain-backup-data"
  type     = "pd-ssd"
  zone     = "${var.backup_region}-a"
  size     = var.data_disk_size_gb

  labels = merge(var.labels, {
    node_role = "backup"
  })
}

# -----------------------------------------------------------------------------
# Compute Instances
# -----------------------------------------------------------------------------

# Admin/Seed Node - Creates and administers the blockchain
resource "google_compute_instance" "admin" {
  name         = "procuchain-admin"
  machine_type = var.admin_node_machine_type
  zone         = var.zone

  tags = ["multichain-node", "multichain-admin"]

  boot_disk {
    initialize_params {
      image = "ubuntu-os-cloud/ubuntu-2204-lts"
      size  = var.boot_disk_size_gb
      type  = "pd-ssd"
    }
  }

  attached_disk {
    source      = google_compute_disk.admin_data.self_link
    device_name = "multichain-data"
  }

  network_interface {
    subnetwork = google_compute_subnetwork.multichain.self_link
    # No external IP - use IAP for SSH
  }

  service_account {
    email  = google_service_account.multichain_node.email
    scopes = ["cloud-platform"]
  }

  metadata = {
    node-role          = "admin"
    chain-name         = var.chain_name
    multichain-version = var.multichain_version
    rpc-port           = var.rpc_port
    p2p-port           = var.p2p_port
    rpc-username       = var.rpc_username
    secret-id          = google_secret_manager_secret.rpc_password.secret_id
    enable-oslogin     = "TRUE"
  }

  metadata_startup_script = file("${path.module}/scripts/startup-admin.sh")

  labels = merge(var.labels, {
    node_role = "admin"
  })

  allow_stopping_for_update = true

  shielded_instance_config {
    enable_secure_boot          = true
    enable_vtpm                 = true
    enable_integrity_monitoring = true
  }
}

# Application Node - Primary (handles web app RPC)
resource "google_compute_instance" "app_primary" {
  name         = "procuchain-app-primary"
  machine_type = var.app_node_machine_type
  zone         = var.zone

  tags = ["multichain-node", "multichain-app-node"]

  boot_disk {
    initialize_params {
      image = "ubuntu-os-cloud/ubuntu-2204-lts"
      size  = var.boot_disk_size_gb
      type  = "pd-ssd"
    }
  }

  attached_disk {
    source      = google_compute_disk.app_primary_data.self_link
    device_name = "multichain-data"
  }

  network_interface {
    subnetwork = google_compute_subnetwork.multichain.self_link
  }

  service_account {
    email  = google_service_account.multichain_node.email
    scopes = ["cloud-platform"]
  }

  metadata = {
    node-role          = "app"
    chain-name         = var.chain_name
    multichain-version = var.multichain_version
    rpc-port           = var.rpc_port
    p2p-port           = var.p2p_port
    rpc-username       = var.rpc_username
    secret-id          = google_secret_manager_secret.rpc_password.secret_id
    admin-node-ip      = google_compute_instance.admin.network_interface[0].network_ip
    enable-oslogin     = "TRUE"
  }

  metadata_startup_script = file("${path.module}/scripts/startup-peer.sh")

  labels = merge(var.labels, {
    node_role = "app-primary"
  })

  allow_stopping_for_update = true

  shielded_instance_config {
    enable_secure_boot          = true
    enable_vtpm                 = true
    enable_integrity_monitoring = true
  }

  depends_on = [google_compute_instance.admin]
}

# Application Node - Secondary (handles queue worker RPC)
resource "google_compute_instance" "app_secondary" {
  name         = "procuchain-app-secondary"
  machine_type = var.app_node_machine_type
  zone         = var.zone

  tags = ["multichain-node", "multichain-app-node"]

  boot_disk {
    initialize_params {
      image = "ubuntu-os-cloud/ubuntu-2204-lts"
      size  = var.boot_disk_size_gb
      type  = "pd-ssd"
    }
  }

  attached_disk {
    source      = google_compute_disk.app_secondary_data.self_link
    device_name = "multichain-data"
  }

  network_interface {
    subnetwork = google_compute_subnetwork.multichain.self_link
  }

  service_account {
    email  = google_service_account.multichain_node.email
    scopes = ["cloud-platform"]
  }

  metadata = {
    node-role          = "app"
    chain-name         = var.chain_name
    multichain-version = var.multichain_version
    rpc-port           = var.rpc_port
    p2p-port           = var.p2p_port
    rpc-username       = var.rpc_username
    secret-id          = google_secret_manager_secret.rpc_password.secret_id
    admin-node-ip      = google_compute_instance.admin.network_interface[0].network_ip
    enable-oslogin     = "TRUE"
  }

  metadata_startup_script = file("${path.module}/scripts/startup-peer.sh")

  labels = merge(var.labels, {
    node_role = "app-secondary"
  })

  allow_stopping_for_update = true

  shielded_instance_config {
    enable_secure_boot          = true
    enable_vtpm                 = true
    enable_integrity_monitoring = true
  }

  depends_on = [google_compute_instance.admin]
}

# Witness Node - Independent validator in different zone
resource "google_compute_instance" "witness" {
  name         = "procuchain-witness"
  machine_type = var.witness_node_machine_type
  zone         = var.secondary_zone

  tags = ["multichain-node", "multichain-witness"]

  boot_disk {
    initialize_params {
      image = "ubuntu-os-cloud/ubuntu-2204-lts"
      size  = var.boot_disk_size_gb
      type  = "pd-ssd"
    }
  }

  attached_disk {
    source      = google_compute_disk.witness_data.self_link
    device_name = "multichain-data"
  }

  network_interface {
    subnetwork = google_compute_subnetwork.multichain.self_link
  }

  service_account {
    email  = google_service_account.multichain_node.email
    scopes = ["cloud-platform"]
  }

  metadata = {
    node-role          = "witness"
    chain-name         = var.chain_name
    multichain-version = var.multichain_version
    rpc-port           = var.rpc_port
    p2p-port           = var.p2p_port
    rpc-username       = var.rpc_username
    secret-id          = google_secret_manager_secret.rpc_password.secret_id
    admin-node-ip      = google_compute_instance.admin.network_interface[0].network_ip
    enable-oslogin     = "TRUE"
  }

  metadata_startup_script = file("${path.module}/scripts/startup-peer.sh")

  labels = merge(var.labels, {
    node_role = "witness"
  })

  allow_stopping_for_update = true

  shielded_instance_config {
    enable_secure_boot          = true
    enable_vtpm                 = true
    enable_integrity_monitoring = true
  }

  depends_on = [google_compute_instance.admin]
}

# Backup Node - DR standby in different region
resource "google_compute_instance" "backup" {
  name         = "procuchain-backup"
  machine_type = var.backup_node_machine_type
  zone         = "${var.backup_region}-a"

  tags = ["multichain-node", "multichain-backup"]

  boot_disk {
    initialize_params {
      image = "ubuntu-os-cloud/ubuntu-2204-lts"
      size  = var.boot_disk_size_gb
      type  = "pd-ssd"
    }
  }

  attached_disk {
    source      = google_compute_disk.backup_data.self_link
    device_name = "multichain-data"
  }

  network_interface {
    subnetwork = google_compute_subnetwork.multichain_backup.self_link
  }

  service_account {
    email  = google_service_account.multichain_node.email
    scopes = ["cloud-platform"]
  }

  metadata = {
    node-role          = "backup"
    chain-name         = var.chain_name
    multichain-version = var.multichain_version
    rpc-port           = var.rpc_port
    p2p-port           = var.p2p_port
    rpc-username       = var.rpc_username
    secret-id          = google_secret_manager_secret.rpc_password.secret_id
    admin-node-ip      = google_compute_instance.admin.network_interface[0].network_ip
    enable-oslogin     = "TRUE"
  }

  metadata_startup_script = file("${path.module}/scripts/startup-peer.sh")

  labels = merge(var.labels, {
    node_role = "backup"
  })

  allow_stopping_for_update = true

  shielded_instance_config {
    enable_secure_boot          = true
    enable_vtpm                 = true
    enable_integrity_monitoring = true
  }

  depends_on = [google_compute_instance.admin]
}

# -----------------------------------------------------------------------------
# Internal Load Balancer for Application Nodes
# -----------------------------------------------------------------------------

# Instance group for app nodes
resource "google_compute_instance_group" "app_nodes" {
  name        = "procuchain-app-nodes"
  description = "Instance group for MultiChain application nodes"
  zone        = var.zone

  instances = [
    google_compute_instance.app_primary.self_link,
    google_compute_instance.app_secondary.self_link,
  ]

  named_port {
    name = "rpc"
    port = var.rpc_port
  }
}

# Health check for RPC endpoint
resource "google_compute_health_check" "multichain_rpc" {
  name               = "procuchain-rpc-health"
  check_interval_sec = 10
  timeout_sec        = 5
  healthy_threshold  = 2
  unhealthy_threshold = 3

  tcp_health_check {
    port = var.rpc_port
  }
}

# Backend service
resource "google_compute_region_backend_service" "multichain_rpc" {
  name                  = "procuchain-rpc-backend"
  region                = var.region
  protocol              = "TCP"
  load_balancing_scheme = "INTERNAL"
  health_checks         = [google_compute_health_check.multichain_rpc.id]

  backend {
    group          = google_compute_instance_group.app_nodes.self_link
    balancing_mode = "CONNECTION"
  }
}

# Forwarding rule (internal IP for RPC)
resource "google_compute_forwarding_rule" "multichain_rpc" {
  name                  = "procuchain-rpc-forwarding"
  region                = var.region
  load_balancing_scheme = "INTERNAL"
  backend_service       = google_compute_region_backend_service.multichain_rpc.id
  ports                 = [var.rpc_port]
  network               = google_compute_network.multichain.id
  subnetwork            = google_compute_subnetwork.multichain.id
}

# -----------------------------------------------------------------------------
# Cloud Storage Bucket for Backups
# -----------------------------------------------------------------------------

resource "google_storage_bucket" "backups" {
  count    = var.backup_bucket_name == "" ? 1 : 0
  name     = "procuchain-backups-${var.project_id}"
  location = var.region

  uniform_bucket_level_access = true

  versioning {
    enabled = true
  }

  lifecycle_rule {
    condition {
      age = var.backup_retention_days
    }
    action {
      type = "Delete"
    }
  }

  lifecycle_rule {
    condition {
      num_newer_versions = 5
    }
    action {
      type = "Delete"
    }
  }

  labels = var.labels
}

# -----------------------------------------------------------------------------
# Cloud Monitoring - Uptime Checks
# -----------------------------------------------------------------------------

resource "google_monitoring_uptime_check_config" "admin_node" {
  display_name = "ProcuChain Admin Node"
  timeout      = "10s"
  period       = "60s"

  tcp_check {
    port = var.p2p_port
  }

  monitored_resource {
    type = "uptime_url"
    labels = {
      project_id = var.project_id
      host       = google_compute_instance.admin.network_interface[0].network_ip
    }
  }
}

# -----------------------------------------------------------------------------
# Alert Policy for Node Down
# -----------------------------------------------------------------------------

resource "google_monitoring_alert_policy" "node_down" {
  display_name = "ProcuChain Node Down"
  combiner     = "OR"

  conditions {
    display_name = "MultiChain node is down"

    condition_threshold {
      filter          = "resource.type = \"gce_instance\" AND metric.type = \"compute.googleapis.com/instance/uptime\" AND metadata.user_labels.application = \"procuchain\""
      duration        = "300s"
      comparison      = "COMPARISON_LT"
      threshold_value = 1

      aggregations {
        alignment_period   = "60s"
        per_series_aligner = "ALIGN_RATE"
      }
    }
  }

  notification_channels = []

  documentation {
    content   = "A ProcuChain MultiChain node has been down for more than 5 minutes. Check the node status and logs."
    mime_type = "text/markdown"
  }

  alert_strategy {
    auto_close = "1800s"
  }
}
