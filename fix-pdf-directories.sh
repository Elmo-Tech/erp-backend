#!/bin/bash

# Fix DomPDF directories and permissions
echo "Creating DomPDF required directories..."

# Create font directories
mkdir -p storage/app/fonts
mkdir -p storage/fonts
mkdir -p storage/app/public
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views

# Set proper permissions
chmod -R 755 storage/app/fonts
chmod -R 755 storage/fonts
chmod -R 755 storage/framework

# Create .gitkeep files to ensure directories are tracked
touch storage/app/fonts/.gitkeep
touch storage/fonts/.gitkeep

# Set ownership (uncomment if running as root)
# chown -R www-data:www-data storage/app/fonts
# chown -R www-data:www-data storage/fonts

echo "✅ DomPDF directories created successfully!"
echo "📁 Created: storage/app/fonts/"
echo "📁 Created: storage/fonts/"
echo "🔐 Permissions set to 755"

# Test directory creation
if [ -d "storage/app/fonts" ]; then
    echo "✅ storage/app/fonts exists and is accessible"
else
    echo "❌ Failed to create storage/app/fonts"
fi

if [ -d "storage/fonts" ]; then
    echo "✅ storage/fonts exists and is accessible"
else
    echo "❌ Failed to create storage/fonts"
fi

echo "🚀 Ready for PDF generation!"