#!/bin/bash
# ==========================================================
# MultiChain Full Data Migration Script
# Migrates a complete MultiChain node to a new server
#
# This script handles:
#   - Backup creation on source server
#   - Transfer to destination server
#   - Restoration and verification
#
# Usage:
#   Export mode (run on source server):
#     ./migrate_multichain.sh export
#
#   Import mode (run on destination server):
#     ./migrate_multichain.sh import /path/to/backup.tar.gz
#
#   Full migration (run on source server with SSH access to destination):
#     ./migrate_multichain.sh migrate user@destination-server
#
# Environment Variables:
#   MULTICHAIN_CHAIN_NAME  - Chain name (default: procuchain)
#   MULTICHAIN_DATA_DIR    - Data directory (default: ~/.multichain)
#   BACKUP_DIR             - Backup directory (default: /tmp)
#
# ==========================================================

set -euo pipefail

# Configuration
CHAIN_NAME="${MULTICHAIN_CHAIN_NAME:-procuchain}"
DATA_DIR="${MULTICHAIN_DATA_DIR:-$HOME/.multichain}"
BACKUP_DIR="${BACKUP_DIR:-/tmp}"
CHAIN_DIR="${DATA_DIR}/${CHAIN_NAME}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${CHAIN_NAME}-migration-${TIMESTAMP}.tar.gz"
CHECKSUM_FILE="${BACKUP_FILE}.sha256"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

# Check if multichain is installed
check_multichain_installed() {
    if ! command -v multichaind &> /dev/null; then
        log_error "MultiChain is not installed. Please install it first."
        exit 1
    fi
}

# Check if chain exists
check_chain_exists() {
    if [[ ! -d "$CHAIN_DIR" ]]; then
        log_error "Chain directory not found: $CHAIN_DIR"
        exit 1
    fi
    
    if [[ ! -f "$CHAIN_DIR/params.dat" ]]; then
        log_error "Chain parameters not found. Is '$CHAIN_NAME' a valid chain?"
        exit 1
    fi
}

# Check if node is running
is_node_running() {
    if multichain-cli "$CHAIN_NAME" getinfo &> /dev/null; then
        return 0
    else
        return 1
    fi
}

# Stop the node gracefully
stop_node() {
    log_info "Stopping MultiChain node..."
    
    if is_node_running; then
        multichain-cli "$CHAIN_NAME" stop 2>/dev/null || true
        
        # Wait for node to stop (max 30 seconds)
        for i in {1..30}; do
            if ! is_node_running; then
                log_info "Node stopped successfully"
                return 0
            fi
            sleep 1
        done
        
        log_error "Node did not stop within 30 seconds"
        exit 1
    else
        log_info "Node is not running"
    fi
}

# Start the node
start_node() {
    log_info "Starting MultiChain node..."
    
    if is_node_running; then
        log_warn "Node is already running"
        return 0
    fi
    
    multichaind "$CHAIN_NAME" -daemon
    
    # Wait for node to start (max 60 seconds)
    for i in {1..60}; do
        if is_node_running; then
            log_info "Node started successfully"
            return 0
        fi
        sleep 1
    done
    
    log_error "Node did not start within 60 seconds"
    exit 1
}

# Create backup archive
create_backup() {
    log_info "Creating backup of chain '$CHAIN_NAME'..."
    
    check_chain_exists
    
    # Check disk space
    local chain_size
    chain_size=$(du -sm "$CHAIN_DIR" | cut -f1)
    local available_space
    available_space=$(df -m "$BACKUP_DIR" | tail -1 | awk '{print $4}')
    
    if [[ $available_space -lt $((chain_size * 2)) ]]; then
        log_error "Insufficient disk space. Need ~${chain_size}MB, have ${available_space}MB"
        exit 1
    fi
    
    log_info "Chain size: ${chain_size}MB"
    
    # Warn if node is running
    if is_node_running; then
        log_warn "Node is running. For consistent backup, stopping node..."
        stop_node
        local was_running=1
    else
        local was_running=0
    fi
    
    # Create backup archive
    log_info "Creating archive: $BACKUP_FILE"
    
    tar --exclude="${CHAIN_NAME}/.lock" \
        --exclude="${CHAIN_NAME}/debug.log" \
        -czvf "$BACKUP_FILE" \
        -C "$DATA_DIR" \
        "$CHAIN_NAME"
    
    # Create checksum
    log_info "Creating checksum..."
    sha256sum "$BACKUP_FILE" > "$CHECKSUM_FILE"
    
    # Set secure permissions
    chmod 600 "$BACKUP_FILE" "$CHECKSUM_FILE"
    
    # Restart node if it was running
    if [[ $was_running -eq 1 ]]; then
        start_node
    fi
    
    log_info "Backup created successfully!"
    log_info "  Archive: $BACKUP_FILE"
    log_info "  Checksum: $CHECKSUM_FILE"
    log_info "  Size: $(du -h "$BACKUP_FILE" | cut -f1)"
    
    echo "$BACKUP_FILE"
}

# Restore from backup
restore_backup() {
    local backup_file="$1"
    
    if [[ ! -f "$backup_file" ]]; then
        log_error "Backup file not found: $backup_file"
        exit 1
    fi
    
    log_info "Restoring from backup: $backup_file"
    
    # Verify checksum if available
    local checksum_file="${backup_file}.sha256"
    if [[ -f "$checksum_file" ]]; then
        log_info "Verifying checksum..."
        if sha256sum -c "$checksum_file"; then
            log_info "Checksum verified"
        else
            log_error "Checksum verification failed!"
            exit 1
        fi
    else
        log_warn "No checksum file found. Skipping verification."
    fi
    
    # Stop node if running
    if is_node_running; then
        stop_node
    fi
    
    # Backup existing data if present
    if [[ -d "$CHAIN_DIR" ]]; then
        local old_backup="${CHAIN_DIR}.old.${TIMESTAMP}"
        log_warn "Existing chain data found. Moving to: $old_backup"
        mv "$CHAIN_DIR" "$old_backup"
    fi
    
    # Create data directory
    mkdir -p "$DATA_DIR"
    
    # Extract backup
    log_info "Extracting backup..."
    tar -xzvf "$backup_file" -C "$DATA_DIR"
    
    # Remove lock file if present
    rm -f "${CHAIN_DIR}/.lock"
    
    log_info "Backup restored successfully!"
}

# Verify restored node
verify_restoration() {
    log_info "Verifying restored node..."
    
    # Check critical files
    local critical_files=("params.dat" "wallet.dat" "multichain.conf")
    for file in "${critical_files[@]}"; do
        if [[ ! -f "${CHAIN_DIR}/${file}" ]]; then
            log_error "Critical file missing: ${file}"
            exit 1
        fi
    done
    log_info "Critical files present"
    
    # Start node
    start_node
    
    # Get node info
    log_info "Node information:"
    multichain-cli "$CHAIN_NAME" getinfo | grep -E "version|chainname|blocks|connections"
    
    # Check wallet
    log_info "Wallet addresses:"
    multichain-cli "$CHAIN_NAME" getaddresses
    
    # Check permissions
    log_info "Checking permissions..."
    local addr
    addr=$(multichain-cli "$CHAIN_NAME" getaddresses | grep -o '"[^"]*"' | head -1 | tr -d '"')
    if [[ -n "$addr" ]]; then
        multichain-cli "$CHAIN_NAME" listpermissions "*" "$addr"
    fi
    
    log_info "Verification complete!"
}

# Full migration to remote server
migrate_to_remote() {
    local destination="$1"
    
    log_info "Starting full migration to: $destination"
    
    # Create backup
    local backup_file
    backup_file=$(create_backup)
    local checksum_file="${backup_file}.sha256"
    
    # Transfer files
    log_info "Transferring backup to destination..."
    scp "$backup_file" "$checksum_file" "${destination}:~/"
    
    # Get remote filename
    local remote_backup
    remote_backup=$(basename "$backup_file")
    
    # Run restore on remote server
    log_info "Running restoration on destination server..."
    ssh "$destination" bash << EOF
        set -e
        
        # Check if MultiChain is installed
        if ! command -v multichaind &> /dev/null; then
            echo "Installing MultiChain on destination..."
            cd /tmp
            wget -q https://www.multichain.com/download/multichain-2.3.3.tar.gz
            tar -xzf multichain-2.3.3.tar.gz
            sudo mv multichain-2.3.3/multichaind multichain-2.3.3/multichain-cli multichain-2.3.3/multichain-util /usr/local/bin/
            rm -rf multichain-2.3.3*
        fi
        
        # Verify checksum
        cd ~
        sha256sum -c "${remote_backup}.sha256"
        
        # Extract backup
        mkdir -p ~/.multichain
        tar -xzf "${remote_backup}" -C ~/.multichain/
        
        # Remove lock file
        rm -f ~/.multichain/${CHAIN_NAME}/.lock
        
        # Start node
        multichaind ${CHAIN_NAME} -daemon
        
        # Wait for startup
        sleep 10
        
        # Verify
        multichain-cli ${CHAIN_NAME} getinfo
        
        echo "Migration completed on destination!"
EOF
    
    log_info "Migration completed successfully!"
    log_info ""
    log_info "Next steps:"
    log_info "  1. Update DNS/firewall to point to new server"
    log_info "  2. Update multichain.conf on new server (RPC settings)"
    log_info "  3. Verify peer connections: multichain-cli $CHAIN_NAME getpeerinfo"
    log_info "  4. After verification, decommission old server"
}

# Show usage
show_usage() {
    cat << EOF
MultiChain Full Data Migration Script

Usage: $0 <command> [options]

Commands:
    export              Create backup archive of the current node
    import <file>       Restore from a backup archive
    migrate <user@host> Full migration to remote server via SSH
    verify              Verify current node installation

Environment Variables:
    MULTICHAIN_CHAIN_NAME   Chain name (default: procuchain)
    MULTICHAIN_DATA_DIR     Data directory (default: ~/.multichain)
    BACKUP_DIR              Backup directory (default: /tmp)

Examples:
    # Create backup on source server
    $0 export
    
    # Restore on destination server
    $0 import /path/to/procuchain-migration-20260101-120000.tar.gz
    
    # Full migration via SSH
    $0 migrate admin@procuchain-admin-new.example.com
    
    # Verify node after migration
    $0 verify

EOF
}

# Main execution
main() {
    local command="${1:-}"
    
    case "$command" in
        export)
            check_multichain_installed
            create_backup
            ;;
        import)
            local backup_file="${2:-}"
            if [[ -z "$backup_file" ]]; then
                log_error "Please specify backup file path"
                show_usage
                exit 1
            fi
            check_multichain_installed
            restore_backup "$backup_file"
            verify_restoration
            ;;
        migrate)
            local destination="${2:-}"
            if [[ -z "$destination" ]]; then
                log_error "Please specify destination (user@host)"
                show_usage
                exit 1
            fi
            check_multichain_installed
            migrate_to_remote "$destination"
            ;;
        verify)
            check_multichain_installed
            check_chain_exists
            verify_restoration
            ;;
        help|--help|-h)
            show_usage
            ;;
        *)
            log_error "Unknown command: $command"
            show_usage
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
