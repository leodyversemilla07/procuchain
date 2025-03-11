# ProcuChain

## Overview

ProcuChain is a blockchain-powered document management system specifically designed for Bids and Awards Committee (BAC) offices. It ensures transparency, security, and immutability in handling procurement documents and processes.

## Features

- Secure document storage and management
- Blockchain-based document verification
- Automated workflow for bids and awards processes
- Real-time tracking of procurement status
- Access control and user management
- Audit trail and document history

## Technology Stack

- Laravel 12
- PHP 8.2+
- Blockchain Integration
- MultiChain
- MySQL Database

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL database
- MultiChain (installed and configured)

```bash
# Clone the repository
git clone https://github.com/leodyversemilla07/procuchain.git
cd procuchain

# Install PHP dependencies
composer install

# Install frontend dependencies
npm install && npm run build

# Configure environment variables
cp .env.example .env
# Edit your .env file with appropriate database and MultiChain settings:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=procuchain
# DB_USERNAME=root
# DB_PASSWORD=
#
# MULTICHAIN_HOST=localhost
# MULTICHAIN_PORT=7447
# MULTICHAIN_CHAIN=procuchain
# MULTICHAIN_USER=multichainrpc
# MULTICHAIN_PASS=your-rpc-password

# Generate application key
php artisan key:generate

# Create and setup the database
php artisan migrate
php artisan db:seed

# Start the development server
composer run dev
```

### MultiChain Setup

```bash
# Install MultiChain (if not already installed)
wget https://www.multichain.com/download/multichain-latest.tar.gz
tar -xvzf multichain-latest.tar.gz
cd multichain-*
mv multichaind multichain-cli multichain-util /usr/local/bin

# Create a new blockchain for ProcuChain
multichain-util create procuchain
multichaind procuchain -daemon
```

## Usage

1. Access the system through your web browser
2. Login with authorized credentials
3. Follow the intuitive interface to manage documents
4. Track and verify documents using blockchain features

## Security

- Laravel security features
- End-to-end encryption
- Decentralized storage
- Role-based access control
- Digital signatures
- Immutable audit logs

## License

MIT License

## Contact

For support or inquiries, please contact [leodyversemilla07@gmail.com]
