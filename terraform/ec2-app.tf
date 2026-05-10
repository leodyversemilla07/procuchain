# Laravel application server
resource "aws_instance" "app" {
  ami           = data.aws_ami.amazon_linux_2023.id
  instance_type = var.app_instance_type
  key_name      = var.ssh_key_name

  subnet_id                   = local.subnet_a
  vpc_security_group_ids      = [aws_security_group.main.id]
  associate_public_ip_address = true

  user_data = templatefile("${path.module}/templates/app-user-data.sh", {
    github_repo_url = var.github_repo_url
    rds_endpoint    = aws_db_instance.main.address
    rds_database    = aws_db_instance.main.db_name
    rds_username    = aws_db_instance.main.username
    rds_password    = aws_db_instance.main.password
    app_key         = var.app_key
    chain_name      = var.multichain_chain_name
    rpc_user        = var.multichain_rpc_user
    rpc_password    = var.multichain_rpc_password
    rpc_port        = var.multichain_rpc_port
  })

  root_block_device {
    volume_size           = 20
    volume_type           = "gp3"
    delete_on_termination = true
    encrypted             = false
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-app"
    Role = "laravel-app"
  })
}

# Elastic IP for the app server
resource "aws_eip" "app" {
  domain   = "vpc"
  instance = aws_instance.app.id

  tags = merge(var.tags, {
    Name = "${var.project_name}-app-eip"
  })
}