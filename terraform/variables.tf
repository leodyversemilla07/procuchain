variable "aws_region" {
  description = "AWS region to deploy resources"
  type        = string
  default     = "us-east-1"
}

variable "project_name" {
  description = "Project name used for resource naming and tagging"
  type        = string
  default     = "procuchain"
}

variable "environment" {
  description = "Deployment environment (production, staging, etc.)"
  type        = string
  default     = "production"
}

variable "instance_type" {
  description = "EC2 instance type for blockchain nodes"
  type        = string
  default     = "t3.micro"
}

variable "app_instance_type" {
  description = "EC2 instance type for the Laravel application server"
  type        = string
  default     = "t3.micro"
}

variable "rds_instance_class" {
  description = "RDS DB instance class"
  type        = string
  default     = "db.t3.micro"
}

variable "ssh_key_name" {
  description = "Name of the EC2 key pair for SSH access"
  type        = string
  default     = "procuchain-prod"
}

variable "allowed_ssh_cidr" {
  description = "CIDR block allowed for SSH access"
  type        = string
  default     = "0.0.0.0/0"
}

variable "rds_master_username" {
  description = "RDS master username"
  type        = string
  default     = "procuchain"
}

variable "rds_master_password" {
  description = "RDS master password"
  type        = string
  sensitive   = true
}

variable "app_key" {
  description = "Laravel APP_KEY (base64 encoded, e.g. base64:...)"
  type        = string
  sensitive   = true
}

variable "github_repo_url" {
  description = "GitHub repository URL for Laravel app"
  type        = string
  default     = "https://github.com/leodyversemilla07/procuchain.git"
}

variable "multichain_chain_name" {
  description = "MultiChain chain name (use a unique name for each blockchain)"
  type        = string
  default     = "procuchain"
}

variable "multichain_rpc_user" {
  description = "MultiChain RPC username"
  type        = string
  default     = "multichainrpc"
}

variable "multichain_rpc_password" {
  description = "MultiChain RPC password"
  type        = string
  default     = "multichainrpc"
}

variable "multichain_rpc_port" {
  description = "MultiChain RPC port"
  type        = number
  default     = 6834
}

variable "multichain_network_port" {
  description = "MultiChain P2P network port"
  type        = number
  default     = 6835
}

variable "node_roles" {
  description = "List of MultiChain node roles"
  type = list(object({
    name = string
    role = string
  }))
  default = [
    { name = "admin", role = "admin" },
    { name = "bac-secretariat", role = "bac-secretariat" },
    { name = "bac-chairman", role = "bac-chairman" },
    { name = "hope", role = "hope" },
  ]
}

variable "tags" {
  description = "Common tags for all resources"
  type        = map(string)
  default = {
    Project     = "procuchain"
    Environment = "production"
    ManagedBy   = "terraform"
  }
}