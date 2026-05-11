output "vpc_id" {
  description = "ID of the VPC"
  value       = data.aws_vpc.default.id
}

output "rds_endpoint" {
  description = "RDS MySQL endpoint"
  value       = aws_db_instance.main.endpoint
}

output "rds_address" {
  description = "RDS MySQL address"
  value       = aws_db_instance.main.address
}

output "app_public_ip" {
  description = "Public IP of the Laravel application server"
  value       = aws_eip.app.public_ip
}

output "app_private_ip" {
  description = "Private IP of the Laravel application server"
  value       = aws_instance.app.private_ip
}

output "app_url" {
  description = "Laravel application URL"
  value       = "http://${aws_eip.app.public_ip}"
}

output "app_security_group_id" {
  description = "Security group ID"
  value       = aws_security_group.main.id
}

# Admin Node Outputs
output "admin_node_public_ip" {
  description = "Public IP of the MultiChain admin node"
  value       = aws_instance.admin_node[0].public_ip
}

output "admin_node_private_ip" {
  description = "Private IP of the MultiChain admin node"
  value       = aws_instance.admin_node[0].private_ip
}

output "multichain_connection_string" {
  description = "Connection string for MultiChain nodes"
  value       = "${var.multichain_chain_name}@${aws_instance.admin_node[0].private_ip}:${var.multichain_network_port}"
}

# Other Nodes Outputs
output "node_public_ips" {
  description = "Map of node names to public IPs"
  value = {
    for k, v in aws_instance.node : k => v.public_ip
  }
}

output "node_private_ips" {
  description = "Map of node names to private IPs"
  value = {
    for k, v in aws_instance.node : k => v.private_ip
  }
}

output "multichain_rpc_url" {
  description = "MultiChain RPC URL for the admin node"
  value       = "http://${var.multichain_rpc_user}:***@${aws_instance.admin_node[0].private_ip}:${var.multichain_rpc_port}"
  sensitive   = true
}

output "multichain_chain_name" {
  description = "Name of the MultiChain blockchain"
  value       = var.multichain_chain_name
}

output "multichain_rpc_port" {
  description = "MultiChain RPC port"
  value       = var.multichain_rpc_port
}

output "multichain_p2p_port" {
  description = "MultiChain P2P network port"
  value       = var.multichain_network_port
}
