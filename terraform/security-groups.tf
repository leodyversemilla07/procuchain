# Main security group for all procuchain resources
resource "aws_security_group" "main" {
  name        = "procuchain-production-e67b2f07"
  description = "Procuchain Production Server"
  vpc_id      = data.aws_vpc.default.id

  tags = merge(var.tags, {
    Name = "procuchain-prod"
  })
}

# SSH from anywhere (or restricted CIDR)
resource "aws_vpc_security_group_ingress_rule" "ssh" {
  security_group_id = aws_security_group.main.id
  cidr_ipv4         = var.allowed_ssh_cidr
  from_port         = 22
  ip_protocol       = "tcp"
  to_port           = 22
  description       = "SSH access"
}

# HTTP for the Laravel app
resource "aws_vpc_security_group_ingress_rule" "http" {
  security_group_id = aws_security_group.main.id
  cidr_ipv4         = "0.0.0.0/0"
  from_port         = 80
  ip_protocol       = "tcp"
  to_port           = 80
  description       = "HTTP access"
}

# HTTPS (for future)
resource "aws_vpc_security_group_ingress_rule" "https" {
  security_group_id = aws_security_group.main.id
  cidr_ipv4         = "0.0.0.0/0"
  from_port         = 443
  ip_protocol       = "tcp"
  to_port           = 443
  description       = "HTTPS access"
}

# MultiChain RPC port (from app server only)
resource "aws_vpc_security_group_ingress_rule" "multichain_rpc" {
  security_group_id            = aws_security_group.main.id
  referenced_security_group_id = aws_security_group.main.id
  from_port                    = var.multichain_rpc_port
  ip_protocol                  = "tcp"
  to_port                      = var.multichain_rpc_port
  description                  = "MultiChain RPC (internal)"
}

# MultiChain P2P network port (between nodes)
resource "aws_vpc_security_group_ingress_rule" "multichain_p2p" {
  security_group_id            = aws_security_group.main.id
  referenced_security_group_id = aws_security_group.main.id
  from_port                    = var.multichain_network_port
  ip_protocol                  = "tcp"
  to_port                      = var.multichain_network_port
  description                  = "MultiChain P2P (between nodes)"
}

# MySQL from app server only
resource "aws_vpc_security_group_ingress_rule" "mysql" {
  security_group_id            = aws_security_group.main.id
  referenced_security_group_id = aws_security_group.main.id
  from_port                    = 3306
  ip_protocol                  = "tcp"
  to_port                      = 3306
  description                  = "MySQL (internal)"
}

# Outbound internet access
resource "aws_vpc_security_group_egress_rule" "all_outbound" {
  security_group_id = aws_security_group.main.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "-1"
  description       = "All outbound traffic"
}