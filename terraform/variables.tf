variable "aws_profile" {
  description = "AWS CLI profile name"
  type        = string
  default     = "adrian"
}

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
 description = "CIDR block allowed for SSH access — override with your IP in secrets.tfvars. Default: VPC-only (use SSM Session Manager for external access)"
 type = string
 default = "172.31.0.0/16"
}

variable "rds_master_username" {
  description = "RDS master username"
  type        = string
  default     = "procuchain"
}

variable "rds_master_password" {
  description = "RDS master password — MUST be set via TF_VAR_rds_master_password env var or .tfvars (gitignored)"
  type        = string
  sensitive   = true
}

variable "app_key" {
  description = "Laravel APP_KEY (base64 encoded) — MUST be set via TF_VAR_app_key env var"
  type        = string
  sensitive   = true
}

variable "github_repo_url" {
  description = "GitHub repository URL for Laravel app"
  type        = string
  default     = "https://github.com/leodyversemilla07/procuchain.git"
}

variable "github_branch" {
  description = "Git branch to deploy"
  type        = string
  default     = "main"
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
  description = "MultiChain RPC password — MUST be set via TF_VAR_multichain_rpc_password"
  type        = string
  sensitive   = true
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
  description = "List of MultiChain node roles — each gets its own EC2 instance in a different AZ for decentralization"
  type = list(object({
    name = string
    role = string
    az   = string
  }))
  default = [
    { name = "admin",            role = "admin",            az = "us-east-1a" },
    { name = "bac-secretariat",  role = "bac-secretariat",  az = "us-east-1b" },
    { name = "bac-chairman",     role = "bac-chairman",     az = "us-east-1c" },
    { name = "hope",             role = "hope",             az = "us-east-1d" },
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
