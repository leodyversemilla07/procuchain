# ============================================
# ADMIN NODE (creates the blockchain)
# Each node gets its own AZ for true decentralization
# ============================================
resource "aws_instance" "admin_node" {
 count = 1

 ami           = data.aws_ami.amazon_linux_2023.id
 instance_type = var.instance_type
 key_name      = var.ssh_key_name

 # SSM instance profile for remote management
 iam_instance_profile = aws_iam_instance_profile.ssm_profile.name

  # Admin goes in its own AZ (us-east-1a by default)
  subnet_id              = lookup(local.az_subnet_map, var.node_roles[0].az, local.app_subnet_id)
  vpc_security_group_ids = [aws_security_group.main.id]
  associate_public_ip_address = true

  user_data = templatefile("${path.module}/templates/multichain-node-user-data.sh", {
    chain_name         = var.multichain_chain_name
    rpc_user           = var.multichain_rpc_user
    rpc_password       = var.multichain_rpc_password
    rpc_port           = tostring(var.multichain_rpc_port)
    network_port       = tostring(var.multichain_network_port)
    multichain_version = "2.3.3"
    node_name          = "admin"
    node_role          = "admin"
    peer_ips           = "{}"
  })

  root_block_device {
    volume_size           = 20
    volume_type           = "gp3"
    delete_on_termination = true
    encrypted             = true
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-admin"
    Role = "admin"
    AZ   = var.node_roles[0].az
  })
}

# ============================================
# PEER NODES (each in its own AZ)
# Connect to admin → MultiChain P2P auto-discovers all peers
# ============================================
resource "aws_instance" "node" {
  for_each = {
    for node in var.node_roles : node.name => node
    if node.name != "admin"
  }

 ami           = data.aws_ami.amazon_linux_2023.id
 instance_type = var.instance_type
 key_name      = var.ssh_key_name

 # SSM instance profile for remote management
 iam_instance_profile = aws_iam_instance_profile.ssm_profile.name
  subnet_id              = lookup(local.az_subnet_map, each.value.az, local.app_subnet_id)
  vpc_security_group_ids = [aws_security_group.main.id]
  associate_public_ip_address = true

  user_data = templatefile("${path.module}/templates/multichain-node-user-data-connect.sh", {
    chain_name         = var.multichain_chain_name
    rpc_user           = var.multichain_rpc_user
    rpc_password       = var.multichain_rpc_password
    rpc_port           = tostring(var.multichain_rpc_port)
    network_port       = tostring(var.multichain_network_port)
    multichain_version = "2.3.3"
    node_name          = each.value.name
    node_role          = each.value.role
    admin_ip           = aws_instance.admin_node[0].private_ip
    peer_ips           = "{}"
  })

  root_block_device {
    volume_size           = 20
    volume_type           = "gp3"
    delete_on_termination = true
    encrypted             = true
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-${each.value.name}"
    Role = each.value.role
    AZ   = each.value.az
  })

  depends_on = [aws_instance.admin_node]
}
