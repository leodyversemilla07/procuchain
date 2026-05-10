# No locals needed - using inline templatefiles
# ============================================
# ADMIN NODE (creates the blockchain)
# ============================================
resource "aws_instance" "admin_node" {
  count         = 1
  ami           = data.aws_ami.amazon_linux_2023.id
  instance_type = var.instance_type
  key_name      = var.ssh_key_name

  subnet_id                   = local.subnet_a
  vpc_security_group_ids      = [aws_security_group.main.id]
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
  })

  root_block_device {
    volume_size           = 20
    volume_type           = "gp3"
    delete_on_termination = true
    encrypted             = false
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-admin"
    Role = "admin"
  })
}

# ============================================
# OTHER NODES (connect to admin)
# ============================================
resource "aws_instance" "node" {
  for_each = {
    for node in var.node_roles : node.name => node
    if node.name != "admin"
  }

  ami           = data.aws_ami.amazon_linux_2023.id
  instance_type = var.instance_type
  key_name      = var.ssh_key_name

  subnet_id                   = local.subnet_a
  vpc_security_group_ids      = [aws_security_group.main.id]
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
    admin_ip           = aws_instance.admin_node[0].public_ip
  })

  root_block_device {
    volume_size           = 20
    volume_type           = "gp3"
    delete_on_termination = true
    encrypted             = false
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-${each.value.name}"
    Role = each.value.role
  })

  # Ensure admin node is created first
  depends_on = [aws_instance.admin_node]
}