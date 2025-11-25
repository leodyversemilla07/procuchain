# MultiChain SSL Setup Guide

## Overview
This guide provides step-by-step instructions for setting up SSL/TLS encryption for your MultiChain blockchain node to secure RPC API connections.

## Prerequisites
- Access to your MultiChain server (IP: 68.183.232.105)
- SSH access to the server
- Root or sudo privileges
- MultiChain blockchain already running

## Current Configuration
- **MultiChain RPC Host**: 68.183.232.105
- **MultiChain RPC Port**: 7450
- **Blockchain Name**: procuchain

## Option 1: Self-Signed Certificate (Testing/Development)

### Step 1: Connect to Your MultiChain Server
```bash
ssh user@68.183.232.105
```

### Step 2: Stop the MultiChain Daemon
```bash
multichain-cli procuchain stop
```

### Step 3: Create SSL Certificate Directory
```bash
sudo mkdir -p /etc/ssl/certs /etc/ssl/private
```

### Step 4: Generate Self-Signed Certificate
```bash
sudo openssl req -x509 -newkey rsa:4096 \
  -keyout /etc/ssl/private/procuchain.key \
  -out /etc/ssl/certs/procuchain.crt \
  -days 365 -nodes \
  -subj '/C=PH/ST=Manila/L=Manila/O=Procuchain/CN=procuchain.tech'
```

### Step 5: Set Proper Permissions
```bash
sudo chmod 600 /etc/ssl/private/procuchain.key
sudo chmod 644 /etc/ssl/certs/procuchain.crt
```

### Step 6: Configure MultiChain for SSL
Edit the multichain.conf file:
```bash
sudo nano ~/.multichain/procuchain/multichain.conf
```

Add these lines to the configuration file:
```ini
rpcssl=1
rpcsslcertificatechainfile=/etc/ssl/certs/procuchain.crt
rpcsslprivatekeyfile=/etc/ssl/private/procuchain.key
rpcsslciphers=TLSv1.2+HIGH:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!SRP:!CAMELLIA
```

### Step 7: Start MultiChain Daemon
```bash
multichaind procuchain -daemon
```

### Step 8: Verify SSL Configuration
```bash
multichain-cli procuchain getinfo
```

## Option 2: Let's Encrypt Certificate (Production Recommended)

### Step 1: Install Certbot
```bash
sudo apt update
sudo apt install certbot
```

### Step 2: Obtain SSL Certificate
```bash
sudo certbot certonly --standalone -d procuchain.tech
```

### Step 3: Configure MultiChain with Let's Encrypt
Edit the multichain.conf file:
```bash
sudo nano ~/.multichain/procuchain/multichain.conf
```

Add these lines:
```ini
rpcssl=1
rpcsslcertificatechainfile=/etc/letsencrypt/live/procuchain.tech/fullchain.pem
rpcsslprivatekeyfile=/etc/letsencrypt/live/procuchain.tech/privkey.pem
rpcsslciphers=TLSv1.2+HIGH:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!SRP:!CAMELLIA
```

### Step 4: Set Up Certificate Auto-Renewal
```bash
sudo crontab -e
```

Add this line to the crontab:
```bash
0 12 * * * /usr/bin/certbot renew --quiet
```

### Step 5: Restart MultiChain
```bash
multichain-cli procuchain stop
multichaind procuchain -daemon
```

## Testing SSL Connection

### From Your Local Machine
Test the SSL connection using OpenSSL:
```bash
openssl s_client -connect 68.183.232.105:7450 -servername procuchain.tech
```

### From MultiChain CLI
```bash
multichain-cli -rpcssl procuchain getinfo
```

## Laravel Application Configuration

After setting up SSL on the MultiChain node, update your Heroku configuration:

```bash
heroku config:set MULTICHAIN_USE_SSL=true MULTICHAIN_VERIFY_SSL=true --app procuchain
```

## Troubleshooting

### Common Issues

1. **Certificate Permission Errors**
   ```bash
   sudo chmod 600 /etc/ssl/private/procuchain.key
   sudo chmod 644 /etc/ssl/certs/procuchain.crt
   ```

2. **MultiChain Won't Start**
   - Check multichain.conf syntax
   - Verify certificate file paths
   - Check MultiChain logs: `tail -f ~/.multichain/procuchain/debug.log`

3. **SSL Connection Refused**
   - Verify firewall allows port 7450
   - Check if rpcssl=1 is set correctly
   - Ensure certificate files exist and are readable

4. **Certificate Verification Failed**
   - For self-signed certificates, set `MULTICHAIN_VERIFY_SSL=false` temporarily
   - For production, ensure certificate chain is complete

### Log Files
- MultiChain logs: `~/.multichain/procuchain/debug.log`
- System logs: `sudo journalctl -u multichaind`

## Security Best Practices

1. **Use Strong Ciphers**: Only allow TLS 1.2+ with secure cipher suites
2. **Certificate Management**: Regularly renew certificates before expiration
3. **Access Control**: Restrict SSH access and use key-based authentication
4. **Firewall**: Only open necessary ports (7450 for MultiChain RPC)
5. **Monitoring**: Monitor certificate expiration and SSL connection health

## Certificate File Locations

### Self-Signed
- Certificate: `/etc/ssl/certs/procuchain.crt`
- Private Key: `/etc/ssl/private/procuchain.key`

### Let's Encrypt
- Certificate: `/etc/letsencrypt/live/procuchain.tech/fullchain.pem`
- Private Key: `/etc/letsencrypt/live/procuchain.tech/privkey.pem`

## Verification Commands

```bash
# Check certificate validity
openssl x509 -in /etc/ssl/certs/procuchain.crt -text -noout

# Test SSL connection
openssl s_client -connect 68.183.232.105:7450 -servername procuchain.tech

# Check MultiChain SSL status
multichain-cli procuchain getruntimeparams | grep ssl
```

## Next Steps

1. Complete SSL setup on MultiChain node
2. Test SSL connection
3. Update Laravel application configuration
4. Monitor SSL certificate expiration
5. Set up automated certificate renewal (for Let's Encrypt)