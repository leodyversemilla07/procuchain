terraform {
  required_version = ">= 1.15"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }

  # Remote state backend (S3 + native locking)
  backend "s3" {
    profile      = "adrian"
    region       = "us-east-1"
    bucket       = "procuchain-terraform-state-722261000462"
    key          = "procuchain/terraform.tfstate"
    encrypt      = true
    use_lockfile = true
  }
}

provider "aws" {
  profile = var.aws_profile
  region  = var.aws_region
}

# Use the default VPC
data "aws_vpc" "default" {
  default = true
}

# Get all subnets in the default VPC
data "aws_subnets" "available" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}

data "aws_subnet" "selected" {
  for_each = toset(data.aws_subnets.available.ids)
  id       = each.value
}

# Look up the Amazon Linux 2023 AMI
data "aws_ami" "amazon_linux_2023" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-*-kernel-6.1-x86_64"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}

locals {
  # Map: AZ name → subnet ID (for spreading nodes across AZs)
  az_subnet_map = {
    for s in data.aws_subnet.selected : s.availability_zone => s.id
  }

  # App server goes in us-east-1a
  app_subnet_id = lookup(local.az_subnet_map, "us-east-1a", data.aws_subnets.available.ids[0])

  # All subnet IDs (for RDS subnet group)
  subnet_ids = sort([for s in data.aws_subnet.selected : s.id])
}
