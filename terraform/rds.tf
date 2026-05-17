# DB subnet group
resource "aws_db_subnet_group" "main" {
  name        = "${var.project_name}-db-subnet-group"
  description = "Subnet group for Procuchain RDS"
  subnet_ids  = local.subnet_ids

  tags = merge(var.tags, {
    Name = "${var.project_name}-db-subnet-group"
  })
}

# RDS MySQL instance
resource "aws_db_instance" "main" {
  identifier     = "${var.project_name}-db"
  engine         = "mysql"
  engine_version = "8.4"
  instance_class = var.rds_instance_class

  db_name  = var.project_name
  username = var.rds_master_username
  password = var.rds_master_password

  allocated_storage = 20
  max_allocated_storage = 0
  storage_type = "gp3"
  storage_encrypted = true

  # Multi-AZ disabled — not supported on AWS free tier (enable when off free tier)
  multi_az = false

  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [aws_security_group.main.id]

  # Backups — max 1 day on AWS free tier (upgrade to 7+ when off free tier)
  backup_retention_period = 1
  backup_window           = "08:25-08:55"
  maintenance_window      = "sun:04:00-sun:05:00"

  auto_minor_version_upgrade  = true
  allow_major_version_upgrade = false
  skip_final_snapshot         = false
  final_snapshot_identifier   = "${var.project_name}-db-final-snapshot"
  deletion_protection         = true

  apply_immediately = true

  tags = merge(var.tags, {
    Name = "${var.project_name}-db"
  })
}
