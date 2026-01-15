# =============================================================================
# ProcuChain MultiChain GCP Infrastructure - Outputs
# =============================================================================

# -----------------------------------------------------------------------------
# Node Information
# -----------------------------------------------------------------------------

output "admin_node" {
  description = "Admin node details"
  value = {
    name        = google_compute_instance.admin.name
    internal_ip = google_compute_instance.admin.network_interface[0].network_ip
    zone        = google_compute_instance.admin.zone
  }
}

output "app_primary_node" {
  description = "Primary application node details"
  value = {
    name        = google_compute_instance.app_primary.name
    internal_ip = google_compute_instance.app_primary.network_interface[0].network_ip
    zone        = google_compute_instance.app_primary.zone
  }
}

output "app_secondary_node" {
  description = "Secondary application node details"
  value = {
    name        = google_compute_instance.app_secondary.name
    internal_ip = google_compute_instance.app_secondary.network_interface[0].network_ip
    zone        = google_compute_instance.app_secondary.zone
  }
}

output "witness_node" {
  description = "Witness node details"
  value = {
    name        = google_compute_instance.witness.name
    internal_ip = google_compute_instance.witness.network_interface[0].network_ip
    zone        = google_compute_instance.witness.zone
  }
}

output "backup_node" {
  description = "Backup node details"
  value = {
    name        = google_compute_instance.backup.name
    internal_ip = google_compute_instance.backup.network_interface[0].network_ip
    zone        = google_compute_instance.backup.zone
  }
}

# -----------------------------------------------------------------------------
# Network Information
# -----------------------------------------------------------------------------

output "vpc_network" {
  description = "VPC network name"
  value       = google_compute_network.multichain.name
}

output "subnet" {
  description = "Subnet details"
  value = {
    name       = google_compute_subnetwork.multichain.name
    cidr_range = google_compute_subnetwork.multichain.ip_cidr_range
    region     = google_compute_subnetwork.multichain.region
  }
}

# -----------------------------------------------------------------------------
# Load Balancer
# -----------------------------------------------------------------------------

output "load_balancer_ip" {
  description = "Internal load balancer IP for RPC connections"
  value       = google_compute_forwarding_rule.multichain_rpc.ip_address
}

output "rpc_endpoint" {
  description = "RPC endpoint for application configuration"
  value       = "${google_compute_forwarding_rule.multichain_rpc.ip_address}:${var.rpc_port}"
}

# -----------------------------------------------------------------------------
# Credentials
# -----------------------------------------------------------------------------

output "rpc_credentials" {
  description = "RPC credentials for application configuration"
  value = {
    username  = var.rpc_username
    secret_id = google_secret_manager_secret.rpc_password.secret_id
  }
  sensitive = true
}

output "secret_manager_secret_name" {
  description = "Secret Manager secret name for RPC password"
  value       = google_secret_manager_secret.rpc_password.name
}

# -----------------------------------------------------------------------------
# Backup
# -----------------------------------------------------------------------------

output "backup_bucket" {
  description = "Cloud Storage bucket for wallet backups"
  value       = var.backup_bucket_name != "" ? var.backup_bucket_name : (length(google_storage_bucket.backups) > 0 ? google_storage_bucket.backups[0].name : null)
}

# -----------------------------------------------------------------------------
# Service Account
# -----------------------------------------------------------------------------

output "service_account_email" {
  description = "Service account email for nodes"
  value       = google_service_account.multichain_node.email
}

# -----------------------------------------------------------------------------
# Connection Instructions
# -----------------------------------------------------------------------------

output "ssh_instructions" {
  description = "SSH connection instructions using IAP"
  value = {
    admin         = "gcloud compute ssh ${google_compute_instance.admin.name} --zone=${google_compute_instance.admin.zone} --tunnel-through-iap"
    app_primary   = "gcloud compute ssh ${google_compute_instance.app_primary.name} --zone=${google_compute_instance.app_primary.zone} --tunnel-through-iap"
    app_secondary = "gcloud compute ssh ${google_compute_instance.app_secondary.name} --zone=${google_compute_instance.app_secondary.zone} --tunnel-through-iap"
    witness       = "gcloud compute ssh ${google_compute_instance.witness.name} --zone=${google_compute_instance.witness.zone} --tunnel-through-iap"
    backup        = "gcloud compute ssh ${google_compute_instance.backup.name} --zone=${google_compute_instance.backup.zone} --tunnel-through-iap"
  }
}

output "laravel_env_config" {
  description = "Environment variables to add to Laravel .env file"
  value       = <<-EOT
    # MultiChain Configuration (Generated by Terraform)
    MULTICHAIN_CHAIN_NAME=${var.chain_name}
    MULTICHAIN_RPC_HOST=${google_compute_forwarding_rule.multichain_rpc.ip_address}
    MULTICHAIN_RPC_PORT=${var.rpc_port}
    MULTICHAIN_RPC_USERNAME=${var.rpc_username}
    # Get password from Secret Manager: gcloud secrets versions access latest --secret="${google_secret_manager_secret.rpc_password.secret_id}"
    MULTICHAIN_RPC_PASSWORD=<GET_FROM_SECRET_MANAGER>
  EOT
  sensitive   = true
}
