#!/bin/bash

# D2 Deployment Script for Ubuntu Server
# This script installs and configures D2 for Laravel integration
# Usage: sudo bash deploy-d2-ubuntu.sh

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=====================================${NC}"
echo -e "${GREEN}D2 Diagram Tool Deployment for Ubuntu${NC}"
echo -e "${GREEN}=====================================${NC}"

# Check if running as root (needed for system-wide installation)
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root (use sudo)${NC}"
    exit 1
fi

# Detect Ubuntu version
UBUNTU_VERSION=$(lsb_release -rs)
echo -e "${YELLOW}Detected Ubuntu version: ${UBUNTU_VERSION}${NC}"

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Step 1: Check if D2 is already installed
if command_exists d2; then
    CURRENT_VERSION=$(d2 --version 2>&1 | grep -oP 'v\K[0-9.]+' || echo "unknown")
    echo -e "${YELLOW}D2 is already installed (version: ${CURRENT_VERSION})${NC}"
    read -p "Do you want to reinstall/update? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${GREEN}Keeping existing D2 installation${NC}"
    else
        echo -e "${YELLOW}Removing existing D2...${NC}"
        rm -f /usr/local/bin/d2
    fi
fi

# Step 2: Install D2 if not present or update requested
if ! command_exists d2; then
    echo -e "${GREEN}Installing D2...${NC}"

    # Detect architecture
    ARCH=$(uname -m)
    case $ARCH in
        x86_64)
            D2_ARCH="amd64"
            ;;
        aarch64|arm64)
            D2_ARCH="arm64"
            ;;
        *)
            echo -e "${RED}Unsupported architecture: $ARCH${NC}"
            exit 1
            ;;
    esac

    # Get latest D2 version
    echo -e "${YELLOW}Fetching latest D2 version...${NC}"
    LATEST_VERSION=$(curl -s https://api.github.com/repos/terrastruct/d2/releases/latest | grep tag_name | cut -d '"' -f 4)

    if [ -z "$LATEST_VERSION" ]; then
        echo -e "${YELLOW}Could not fetch latest version, using v0.6.7${NC}"
        LATEST_VERSION="v0.6.7"
    fi

    echo -e "${GREEN}Downloading D2 ${LATEST_VERSION} for Linux ${D2_ARCH}...${NC}"

    # Download D2
    DOWNLOAD_URL="https://github.com/terrastruct/d2/releases/download/${LATEST_VERSION}/d2-${LATEST_VERSION}-linux-${D2_ARCH}.tar.gz"

    cd /tmp
    wget -q --show-progress "$DOWNLOAD_URL" -O d2.tar.gz

    # Extract and install
    echo -e "${GREEN}Extracting and installing D2...${NC}"
    tar -xzf d2.tar.gz

    # Find the d2 binary (it might be in a subdirectory)
    D2_BIN=$(find . -name "d2" -type f -executable | head -n 1)

    if [ -z "$D2_BIN" ]; then
        echo -e "${RED}Could not find D2 binary in archive${NC}"
        exit 1
    fi

    mv "$D2_BIN" /usr/local/bin/
    chmod +x /usr/local/bin/d2

    # Clean up
    rm -rf d2.tar.gz d2-*

    echo -e "${GREEN}D2 installed successfully!${NC}"
fi

# Step 3: Verify installation
echo -e "${GREEN}Verifying D2 installation...${NC}"
if /usr/local/bin/d2 --version; then
    echo -e "${GREEN}✓ D2 is working correctly${NC}"
else
    echo -e "${RED}✗ D2 installation verification failed${NC}"
    exit 1
fi

# Step 4: Configure for web server user
echo -e "${GREEN}Configuring D2 for web server user (www-data)...${NC}"

# Test if www-data can execute D2
if sudo -u www-data /usr/local/bin/d2 --version >/dev/null 2>&1; then
    echo -e "${GREEN}✓ www-data can execute D2${NC}"
else
    echo -e "${YELLOW}Setting up permissions for www-data...${NC}"
    # Ensure www-data has execute permissions
    chmod 755 /usr/local/bin/d2
fi

# Step 5: Create temp directory for D2 operations
echo -e "${GREEN}Setting up temp directory for D2 diagrams...${NC}"
D2_TEMP_DIR="/var/www/temp/d2"
mkdir -p "$D2_TEMP_DIR"
chown www-data:www-data "$D2_TEMP_DIR"
chmod 755 "$D2_TEMP_DIR"
echo -e "${GREEN}✓ Temp directory created at ${D2_TEMP_DIR}${NC}"

# Step 6: Test D2 with a sample diagram
echo -e "${GREEN}Testing D2 with sample diagram...${NC}"
TEST_D2_FILE="/tmp/test.d2"
cat > "$TEST_D2_FILE" << 'EOF'
Test: "Test Diagram" {
  shape: rectangle
}
Input -> Test -> Output
EOF

if sudo -u www-data /usr/local/bin/d2 "$TEST_D2_FILE" /tmp/test.svg 2>/dev/null; then
    echo -e "${GREEN}✓ D2 can generate diagrams as www-data${NC}"
    rm -f /tmp/test.svg "$TEST_D2_FILE"
else
    echo -e "${YELLOW}⚠ D2 diagram generation test failed (may need additional configuration)${NC}"
fi

# Step 7: Create systemd service for D2 health monitoring (optional)
echo -e "${GREEN}Creating D2 health check script...${NC}"
cat > /usr/local/bin/check-d2-health << 'EOF'
#!/bin/bash
# D2 Health Check Script
if ! /usr/local/bin/d2 --version >/dev/null 2>&1; then
    echo "D2 is not responding"
    exit 1
fi

if ! sudo -u www-data /usr/local/bin/d2 --version >/dev/null 2>&1; then
    echo "www-data cannot execute D2"
    exit 1
fi

echo "D2 is healthy"
exit 0
EOF

chmod +x /usr/local/bin/check-d2-health
echo -e "${GREEN}✓ Health check script created at /usr/local/bin/check-d2-health${NC}"

# Step 8: Add D2 to PATH for all users
echo -e "${GREEN}Adding D2 to system PATH...${NC}"
if ! grep -q "/usr/local/bin" /etc/environment; then
    sed -i 's|PATH="\(.*\)"|PATH="\1:/usr/local/bin"|g' /etc/environment
    echo -e "${GREEN}✓ Added /usr/local/bin to PATH${NC}"
else
    echo -e "${YELLOW}✓ /usr/local/bin already in PATH${NC}"
fi

# Step 9: Create Laravel environment configuration
echo -e "${GREEN}Creating Laravel D2 configuration file...${NC}"
ENV_CONFIG="/tmp/d2-env-config.txt"
cat > "$ENV_CONFIG" << 'EOF'

# D2 Diagram Configuration
D2_BINARY_PATH=/usr/local/bin/d2
D2_TEMP_PATH=/var/www/temp/d2
D2_TIMEOUT=10
D2_CACHE_TTL=3600
D2_ENABLED=true
EOF

echo -e "${YELLOW}Add the following to your Laravel .env file:${NC}"
cat "$ENV_CONFIG"

# Step 10: Summary
echo -e "${GREEN}=====================================${NC}"
echo -e "${GREEN}D2 Deployment Complete!${NC}"
echo -e "${GREEN}=====================================${NC}"
echo
echo -e "${GREEN}Summary:${NC}"
echo -e "  • D2 installed at: /usr/local/bin/d2"
echo -e "  • Version: $(d2 --version 2>&1 | grep -oP 'v\K[0-9.]+' || echo 'unknown')"
echo -e "  • Temp directory: ${D2_TEMP_DIR}"
echo -e "  • Health check: /usr/local/bin/check-d2-health"
echo
echo -e "${YELLOW}Next steps:${NC}"
echo -e "  1. Add the environment variables to your Laravel .env file"
echo -e "  2. Clear Laravel cache: php artisan config:clear"
echo -e "  3. Test the integration: php artisan d2:health"
echo
echo -e "${GREEN}Installation complete! D2 is ready for production.${NC}"