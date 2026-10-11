#!/bin/bash

# 🚀 Business OS Deployment Script
# Version: 1.0.0
# Last Updated: January 22, 2026

set -e  # Exit on any error

echo "🚀 Starting Business OS Deployment..."
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "erp-backend/artisan" ]; then
    print_error "Please run this script from the project root directory"
    exit 1
fi

# Navigate to backend directory
cd erp-backend

print_status "Updating codebase..."
git pull origin main

print_status "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

print_status "Setting up environment..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    print_warning "Please configure your .env file before continuing"
    read -p "Press enter to continue after configuring .env..."
fi

print_status "Generating application key..."
php artisan key:generate --force

print_status "Running database migrations..."
php artisan migrate --force

print_status "Resetting permission cache..."
php artisan permission:cache-reset 2>/dev/null || print_warning "permission:cache-reset not available, continuing"

print_status "🔧 Setting up PDF directories (CRITICAL)..."
if php artisan list | grep -q "pdf:setup-directories"; then
    php artisan pdf:setup-directories
    print_success "PDF directories created successfully"
else
    print_warning "PDF setup command not found, creating directories manually..."
    mkdir -p storage/app/fonts
    mkdir -p storage/fonts
    mkdir -p storage/framework/cache
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/app/public
    
    # Create .gitkeep files
    touch storage/app/fonts/.gitkeep
    touch storage/fonts/.gitkeep
    
    print_success "PDF directories created manually"
fi

print_status "Setting proper permissions..."
chmod -R 755 storage bootstrap/cache
if command -v chown >/dev/null 2>&1; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || print_warning "Could not set ownership (may need sudo)"
fi

print_status "Clearing and caching configurations..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

print_status "Generating API documentation..."
php artisan l5-swagger:generate

print_status "Creating storage link..."
php artisan storage:link

# Test PDF generation
print_status "Testing PDF generation capabilities..."
if [ -d "storage/app/fonts" ] && [ -w "storage/app/fonts" ]; then
    print_success "PDF directories are ready and writable"
else
    print_error "PDF directories are not properly set up"
    exit 1
fi

# Test write permissions
TEST_FILE="storage/app/fonts/test_write.tmp"
if echo "test" > "$TEST_FILE" 2>/dev/null; then
    rm "$TEST_FILE"
    print_success "Write permissions verified"
else
    print_error "Write permissions test failed"
    exit 1
fi

print_status "Optimizing application..."
php artisan optimize

# Check if supervisor is available for queue workers
if command -v supervisorctl >/dev/null 2>&1; then
    print_status "Restarting queue workers..."
    sudo supervisorctl restart laravel-worker:* 2>/dev/null || print_warning "Could not restart queue workers (may need manual restart)"
else
    print_warning "Supervisor not found. Please set up queue workers manually."
fi

# Return to project root
cd ..

# Frontend deployment (if directory exists)
if [ -d "erp-frontend" ]; then
    print_status "Deploying frontend..."
    cd erp-frontend
    
    if [ -f "package.json" ]; then
        print_status "Installing frontend dependencies..."
        npm install
        
        print_status "Building frontend..."
        npm run build
        
        print_success "Frontend built successfully"
    fi
    
    cd ..
fi

print_success "🎉 Deployment completed successfully!"
echo ""
echo "📋 Post-deployment checklist:"
echo "  1. Verify .env configuration"
echo "  2. Test PDF generation endpoint"
echo "  3. Check application logs"
echo "  4. Verify queue workers are running"
echo "  5. Test critical application features"
echo ""
echo "🔍 Useful commands:"
echo "  • Check logs: tail -f erp-backend/storage/logs/laravel.log"
echo "  • Test PDF: curl -H 'Authorization: Bearer TOKEN' 'https://domain.com/api/v1/admin/stats/employee-review/1/export'"
echo "  • Queue status: php artisan queue:work --once"
echo ""
print_success "Deployment script completed! 🚀"