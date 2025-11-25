#!/bin/bash

# MultiChain SSL Setup Script
# This script sets up SSL for your MultiChain blockchain node

set -e

# Configuration
BLOCKCHAIN_NAME="procuchain"
CERT_DIR="/etc/ssl"
DOMAIN="procuchain.tech"
COUNTRY="PH"
STATE="Manila"
CITY="Manila"
ORG="Procuchain"

echo "=== MultiChain SSL Setup Script ==="
echo "Blockchain: $BLOCKCHAIN_NAME"
echo "Domain: $DOMAIN"
echo ""

# Function to check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        echo "This script must be run as root (sudo)"
        exit 1
    fi
}

# Function to stop MultiChain
stop_multichain() {
    echo "Stopping MultiChain daemon..."
    if multichain-cli $BLOCKCHAIN_NAME stop 2>/dev/null; then
        echo "✓ MultiChain stopped successfully"
        sleep 2
    else
        echo "! MultiChain may not be running"
    fi
}

# Function to start MultiChain
start_multichain() {
    echo "Starting MultiChain daemon..."
    if multichaind $BLOCKCHAIN_NAME -daemon; then
        echo "✓ MultiChain started successfully"
        sleep 3
    else
        echo "✗ Failed to start MultiChain"
        exit 1
    fi
}

# Function to setup self-signed certificate
setup_self_signed() {
    echo "Setting up self-signed SSL certificate..."

    # Create certificate directory
    mkdir -p $CERT_DIR/certs $CERT_DIR/private

    # Generate certificate
    openssl req -x509 -newkey rsa:4096 \
        -keyout $CERT_DIR/private/$BLOCKCHAIN_NAME.key \
        -out $CERT_DIR/certs/$BLOCKCHAIN_NAME.crt \
        -days 365 -nodes \
        -subj "/C=$COUNTRY/ST=$STATE/L=$CITY/O=$ORG/CN=$DOMAIN" \
        2>/dev/null

    # Set permissions
    chmod 600 $CERT_DIR/private/$BLOCKCHAIN_NAME.key
    chmod 644 $CERT_DIR/certs/$BLOCKCHAIN_NAME.crt

    echo "✓ Self-signed certificate created"
    echo "  Certificate: $CERT_DIR/certs/$BLOCKCHAIN_NAME.crt"
    echo "  Private Key: $CERT_DIR/private/$BLOCKCHAIN_NAME.key"
}

# Function to configure MultiChain
configure_multichain() {
    echo "Configuring MultiChain for SSL..."

    local CONF_FILE="$HOME/.multichain/$BLOCKCHAIN_NAME/multichain.conf"

    # Backup existing config
    if [[ -f "$CONF_FILE" ]]; then
        cp "$CONF_FILE" "$CONF_FILE.backup.$(date +%Y%m%d_%H%M%S)"
        echo "✓ Configuration backed up"
    fi

    # Add SSL configuration
    cat >> "$CONF_FILE" << EOF

# SSL Configuration
rpcssl=1
rpcsslcertificatechainfile=$CERT_DIR/certs/$BLOCKCHAIN_NAME.crt
rpcsslprivatekeyfile=$CERT_DIR/private/$BLOCKCHAIN_NAME.key
rpcsslciphers=TLSv1.2+HIGH:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!SRP:!CAMELLIA
EOF

    echo "✓ SSL configuration added to multichain.conf"
}

# Function to test SSL
test_ssl() {
    echo "Testing SSL configuration..."

    # Test certificate
    if openssl x509 -in $CERT_DIR/certs/$BLOCKCHAIN_NAME.crt -text -noout >/dev/null 2>&1; then
        echo "✓ Certificate is valid"
    else
        echo "✗ Certificate validation failed"
        return 1
    fi

    # Test MultiChain connection
    if multichain-cli $BLOCKCHAIN_NAME getinfo >/dev/null 2>&1; then
        echo "✓ MultiChain SSL connection successful"
    else
        echo "✗ MultiChain SSL connection failed"
        return 1
    fi
}

# Main execution
main() {
    echo "Select SSL setup option:"
    echo "1) Self-signed certificate (testing/development)"
    echo "2) Let's Encrypt certificate (production)"
    read -p "Enter choice (1 or 2): " choice

    case $choice in
        1)
            check_root
            stop_multichain
            setup_self_signed
            configure_multichain
            start_multichain
            test_ssl
            ;;
        2)
            echo "For Let's Encrypt setup, please run:"
            echo "sudo apt update && sudo apt install certbot"
            echo "sudo certbot certonly --standalone -d $DOMAIN"
            echo "Then manually configure multichain.conf with the certificate paths"
            exit 0
            ;;
        *)
            echo "Invalid choice"
            exit 1
            ;;
    esac

    if [[ $? -eq 0 ]]; then
        echo ""
        echo "=== SSL Setup Complete ==="
        echo "Your MultiChain node is now configured with SSL!"
        echo ""
        echo "Next steps:"
        echo "1. Update your Laravel app config:"
        echo "   heroku config:set MULTICHAIN_USE_SSL=true MULTICHAIN_VERIFY_SSL=true --app procuchain"
        echo ""
        echo "2. Test the connection from your application"
        echo ""
        echo "3. For production, consider using Let's Encrypt certificates"
    fi
}

# Run main function
main "$@"