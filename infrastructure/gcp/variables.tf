# =============================================================================
# ProcuChain MultiChain GCP Infrastructure - Variables
# =============================================================================

variable "project_id" {
  description = "GCP Project ID"
  type        = string
}

variable "region" {
  description = "Primary GCP region for deployment"
  type        = string
  default     = "asia-southeast1" # Singapore - closest to Philippines
}

variable "zone" {
  description = "Primary GCP zone"
  type        = string
  default     = "asia-southeast1-b"
}

variable "secondary_zone" {
  description = "Secondary zone for witness/backup nodes (different failure domain)"
  type        = string
  default     = "asia-southeast1-c"
}

variable "backup_region" {
  description = "Backup region for disaster recovery"
  type        = string
  default     = "asia-northeast1" # Tokyo
}

variable "environment" {
  description = "Environment name (dev, staging, production)"
  type        = string
  default     = "production"
}

# -----------------------------------------------------------------------------
# Network Configuration
# -----------------------------------------------------------------------------

variable "vpc_name" {
  description = "Name of the VPC network"
  type        = string
  default     = "procuchain-vpc"
}

variable "subnet_cidr" {
  description = "CIDR range for the private subnet"
  type        = string
  default     = "10.0.1.0/24"
}

# -----------------------------------------------------------------------------
# MultiChain Configuration
# -----------------------------------------------------------------------------

variable "chain_name" {
  description = "MultiChain blockchain name"
  type        = string
  default     = "procuchain"
}

variable "multichain_version" {
  description = "MultiChain version to install"
  type        = string
  default     = "2.3.3"
}

variable "rpc_port" {
  description = "MultiChain RPC port"
  type        = number
  default     = 6486
}

variable "p2p_port" {
  description = "MultiChain P2P port"
  type        = number
  default     = 6487
}

variable "rpc_username" {
  description = "MultiChain RPC username"
  type        = string
  default     = "multichainrpc"
  sensitive   = true
}

# -----------------------------------------------------------------------------
# Compute Instance Configuration
# -----------------------------------------------------------------------------

variable "admin_node_machine_type" {
  description = "Machine type for admin node"
  type        = string
  default     = "n2-standard-2" # 2 vCPU, 8GB RAM
}

variable "app_node_machine_type" {
  description = "Machine type for application nodes"
  type        = string
  default     = "n2-standard-2" # 2 vCPU, 8GB RAM
}

variable "witness_node_machine_type" {
  description = "Machine type for witness node"
  type        = string
  default     = "e2-medium" # 2 vCPU, 4GB RAM
}

variable "backup_node_machine_type" {
  description = "Machine type for backup node"
  type        = string
  default     = "e2-medium" # 2 vCPU, 4GB RAM
}

variable "boot_disk_size_gb" {
  description = "Boot disk size in GB"
  type        = number
  default     = 20
}

variable "data_disk_size_gb" {
  description = "Data disk size in GB for blockchain storage"
  type        = number
  default     = 50
}

variable "witness_data_disk_size_gb" {
  description = "Data disk size for witness node"
  type        = number
  default     = 30
}

# -----------------------------------------------------------------------------
# Access Control
# -----------------------------------------------------------------------------

variable "allowed_ssh_ranges" {
  description = "CIDR ranges allowed to SSH into nodes (e.g., your office IP)"
  type        = list(string)
  default     = []
}

variable "app_server_ips" {
  description = "IP addresses of application servers allowed to connect to RPC"
  type        = list(string)
  default     = []
}

# -----------------------------------------------------------------------------
# Backup Configuration
# -----------------------------------------------------------------------------

variable "backup_bucket_name" {
  description = "Cloud Storage bucket name for wallet backups"
  type        = string
  default     = ""
}

variable "backup_retention_days" {
  description = "Number of days to retain backups"
  type        = number
  default     = 30
}

# -----------------------------------------------------------------------------
# Labels
# -----------------------------------------------------------------------------

variable "labels" {
  description = "Labels to apply to all resources"
  type        = map(string)
  default = {
    application = "procuchain"
    component   = "blockchain"
    managed_by  = "terraform"
  }
}
