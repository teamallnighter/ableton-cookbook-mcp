#!/bin/bash

# 🔑 Setup GitHub Actions SSH Access
# This script configures your server for GitHub Actions deployment

set -e

echo "🔑 Setting up GitHub Actions SSH access..."

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${GREEN}✅ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
print_error() { echo -e "${RED}❌ $1${NC}"; }
print_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }

# Check if running as deploy user
if [ "$USER" != "deploy" ]; then
    print_error "Please run as deploy user: su - deploy"
    exit 1
fi

print_status "Step 1: Creating SSH key for GitHub Actions"
cd ~
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Generate SSH key if it doesn't exist
if [ ! -f ~/.ssh/github_actions ]; then
    ssh-keygen -t ed25519 -f ~/.ssh/github_actions -N "" -C "github-actions@ableton-cookbook"
    print_status "SSH key generated!"
else
    print_warning "SSH key already exists, skipping generation"
fi

print_status "Step 2: Setting up authorized_keys"
if [ ! -f ~/.ssh/authorized_keys ]; then
    touch ~/.ssh/authorized_keys
    chmod 600 ~/.ssh/authorized_keys
fi

# Add the public key to authorized_keys if not already there
if ! grep -q "github-actions@ableton-cookbook" ~/.ssh/authorized_keys; then
    cat ~/.ssh/github_actions.pub >> ~/.ssh/authorized_keys
    print_status "Public key added to authorized_keys"
else
    print_warning "Public key already in authorized_keys"
fi

print_status "Step 3: Setting up sudo permissions for deployment"
print_info "You'll need to add this to /etc/sudoers.d/deploy (run as root):"
echo -e "${BLUE}deploy ALL=(ALL) NOPASSWD: /bin/bash /var/www/ableton-cookbook/laravel-app/deployment/production-deploy-safe.sh${NC}"

print_status "Step 4: Displaying private key for GitHub secrets"
echo ""
print_warning "=== COPY THIS PRIVATE KEY TO GITHUB SECRETS ==="
echo -e "${YELLOW}Secret name: SSH_KEY${NC}"
echo ""
cat ~/.ssh/github_actions
echo ""
print_warning "=== END OF PRIVATE KEY ==="
echo ""

print_status "Step 5: Server connection details"
echo -e "${BLUE}Add these secrets to your GitHub repository:${NC}"
echo -e "${YELLOW}HOST:${NC} $(curl -s ifconfig.me || hostname -I | awk '{print $1}')"
echo -e "${YELLOW}USERNAME:${NC} deploy"
echo -e "${YELLOW}SSH_KEY:${NC} (the private key shown above)"

echo ""
print_info "Next steps:"
echo "1. Copy the private key above"
echo "2. Go to GitHub → Settings → Secrets and variables → Actions"
echo "3. Add the three secrets: HOST, USERNAME, SSH_KEY"
echo "4. Run as root: echo 'deploy ALL=(ALL) NOPASSWD: /bin/bash /var/www/ableton-cookbook/laravel-app/deployment/production-deploy-safe.sh' > /etc/sudoers.d/deploy"
echo "5. Test deployment by pushing to main branch"

print_status "🎉 GitHub Actions setup complete!"