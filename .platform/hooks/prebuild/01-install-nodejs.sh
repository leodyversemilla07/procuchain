#!/usr/bin/env bash
# Install Node.js 22 on AL2023 for Vite frontend build
# EB PHP platform doesn't include Node.js by default

set -e

NODE_VERSION="22"

# Check if correct Node.js is already installed
if command -v node &>/dev/null; then
  CURRENT_VERSION=$(node --version 2>/dev/null | grep -oP '^v\K\d+')
  if [ "$CURRENT_VERSION" = "$NODE_VERSION" ]; then
    echo "Node.js $NODE_VERSION already installed"
    exit 0
  fi
fi

# Install Node.js 22 via Amazon Linux 2023 native packages
echo "Installing Node.js $NODE_VERSION..."
dnf install -y nodejs22 nodejs22-npm 2>/dev/null || {
  # Fallback: install via NodeSource if AL2023 package not available
  curl -fsSL https://rpm.nodesource.com/setup_${NODE_VERSION}.x | bash -
  dnf install -y nodejs
}

node --version
npm --version
echo "Node.js installation complete"
