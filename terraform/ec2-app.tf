# Laravel application server
resource "aws_instance" "app" {
 ami           = data.aws_ami.amazon_linux_2023.id
 instance_type = var.app_instance_type
 key_name      = var.ssh_key_name

 # SSM instance profile for remote management
 iam_instance_profile = aws_iam_instance_profile.ssm_profile.name

  subnet_id              = local.app_subnet_id
  vpc_security_group_ids = [aws_security_group.main.id]
  associate_public_ip_address = true

  user_data = templatefile("${path.module}/templates/app-user-data.sh", {
    github_repo_url = var.github_repo_url
    github_branch   = var.github_branch
    rds_endpoint    = aws_db_instance.main.address
    rds_database    = aws_db_instance.main.db_name
    rds_username    = aws_db_instance.main.username
    rds_password    = var.rds_master_password
    app_key         = var.app_key
    chain_name      = var.multichain_chain_name
    rpc_user        = var.multichain_rpc_user
    rpc_password    = var.multichain_rpc_password
    rpc_port        = var.multichain_rpc_port
    # Admin node is the primary RPC endpoint for the app
    admin_node_ip = aws_instance.admin_node[0].private_ip
    # Node IPs built directly — no self-referencing local needed
    node_ips = jsonencode(merge(
      { "admin" = {
          private_ip = aws_instance.admin_node[0].private_ip
          public_ip  = aws_instance.admin_node[0].public_ip
          role       = "admin"
        }
      },
      { for k, v in aws_instance.node : k => {
          private_ip = v.private_ip
          public_ip  = v.public_ip
          role       = v.tags.Role
        }
      }
    ))
  })

  root_block_device {
    volume_size           = 20
    volume_type           = "gp3"
    delete_on_termination = true
    encrypted             = true
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-app"
    Role = "laravel-app"
  })

  depends_on = [aws_instance.admin_node, aws_instance.node]
}

# Elastic IP for the app server
resource "aws_eip" "app" {
  domain   = "vpc"
  instance = aws_instance.app.id

  tags = merge(var.tags, {
    Name = "${var.project_name}-app-eip"
  })
}
