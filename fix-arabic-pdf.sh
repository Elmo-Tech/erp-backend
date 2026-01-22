#!/bin/bash

# Arabic PDF Fix Script for Laravel dompdf
# This script sets up proper Arabic font support for PDF generation

echo "🔧 Setting up Arabic PDF support..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# Check if we're in Laravel root
if [ ! -f "artisan" ]; then
    print_error "This script must be run from Laravel root directory"
    exit 1
fi

print_info "Starting Arabic PDF setup process..."

# Step 1: Setup directories
print_info "Step 1: Setting up directories..."
php artisan pdf:setup-directories
if [ $? -eq 0 ]; then
    print_status "Directories created successfully"
else
    print_error "Failed to create directories"
    exit 1
fi

# Step 2: Download and install fonts
print_info "Step 2: Installing Arabic fonts..."
php artisan pdf:install-fonts --download
if [ $? -eq 0 ]; then
    print_status "Fonts installed successfully"
else
    print_warning "Font installation had issues, but continuing..."
fi

# Step 3: Set proper permissions
print_info "Step 3: Setting permissions..."
chmod -R 755 storage/app/fonts
chmod -R 755 storage/fonts
print_status "Permissions set"

# Step 4: Clear caches
print_info "Step 4: Clearing caches..."
php artisan config:clear
php artisan view:clear
print_status "Caches cleared"

# Step 5: Test Arabic PDF generation
print_info "Step 5: Testing Arabic PDF generation..."
php artisan pdf:test-arabic --save
if [ $? -eq 0 ]; then
    print_status "Arabic PDF test passed!"
else
    print_error "Arabic PDF test failed"
    print_info "Check the error messages above for troubleshooting"
fi

echo ""
print_info "=== Setup Complete ==="
print_info "If tests passed, Arabic text should now display correctly in PDFs"
print_info "Generated test PDFs are saved in storage/app/public/"
echo ""
print_info "Troubleshooting tips:"
echo "1. Ensure storage directories have write permissions"
echo "2. Check if Arabic fonts were downloaded successfully"
echo "3. Verify dompdf configuration in config/dompdf.php"
echo "4. Test with: php artisan pdf:test-arabic"