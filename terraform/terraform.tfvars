aws_region            = "us-east-1"
project_name          = "procuchain"
environment           = "production"

# RDS
rds_master_username   = "procuchain"

# SSH — restrict this! 0.0.0.0/0 is open to the world
allowed_ssh_cidr      = "0.0.0.0/0"

# MultiChain
multichain_rpc_user   = "multichainrpc"
