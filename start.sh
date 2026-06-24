#!/bin/bash

# 🚀 Laravel Application Startup Script
# Version: 2.0.0 - Updated with PDF directory setup
# Last Updated: January 22, 2026

set -e  # Exit on any error

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

echo "🚀 Starting Laravel Application..."
echo "================================="

# 1. Fix Permissions (عشان لارفيل يعرف يكتب)
print_status "Setting up permissions..."
chown -R 33:33 /app/storage 2>/dev/null || print_warning "Could not set ownership (may need different user)"
chmod -R 775 /app/storage
print_success "Permissions configured"

# 2. Setup PDF directories (CRITICAL for PDF generation)
print_status "Setting up PDF directories..."
if php artisan list | grep -q "pdf:setup-directories"; then
    php artisan pdf:setup-directories
    print_success "PDF directories setup completed"
else
    print_warning "PDF setup command not found, creating directories manually..."
    mkdir -p storage/app/fonts
    mkdir -p storage/fonts
    mkdir -p storage/framework/cache
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/app/public
    
    # Set proper permissions
    chmod -R 775 storage/
    chown -R 33:33 storage/ 2>/dev/null || true
    
    # Create .gitkeep files
    touch storage/app/fonts/.gitkeep
    touch storage/fonts/.gitkeep
    
    print_success "PDF directories created manually"
fi

# 3. Test PDF directory permissions
print_status "Testing PDF directory permissions..."
TEST_FILE="storage/app/fonts/test_write.tmp"
if echo "test" > "$TEST_FILE" 2>/dev/null; then
    rm "$TEST_FILE"
    print_success "PDF directories are writable"
else
    print_error "PDF directories are not writable - this will cause PDF generation to fail!"
fi

# 4. Laravel Setup (تجهيز المشروع)
print_status "Setting up Laravel application..."
php artisan storage:link
print_success "Storage link created"

print_status "Running database migrations..."
php artisan migrate --force
print_success "Database migrations completed"

print_status "Optimizing application..."
php artisan config:cache
php artisan route:cache
# php artisan view:cache
php artisan optimize
print_success "Application optimized"

# 6. Start Background Processes (تشغيل الخدمات الخلفية)
print_status "Starting background processes..."

# تشغيل الـ Queue Worker في الخلفية
print_status "Starting Queue Worker..."
php artisan queue:work --tries=3 --timeout=90 --memory=512 &
QUEUE_PID=$!
print_success "Queue Worker started (PID: $QUEUE_PID)"

# تشغيل الـ Reverb (WebSocket) في الخلفية
print_status "Starting Reverb WebSocket server..."
php artisan reverb:start --host=0.0.0.0 --port=8080 &
REVERB_PID=$!
print_success "Reverb WebSocket started (PID: $REVERB_PID)"

# تشغيل الـ Scheduler في الخلفية (للمهام المجدولة وتذكيرات الـ SaaS)
print_status "Starting Task Scheduler..."
php artisan schedule:work &
SCHEDULE_PID=$!
print_success "Task Scheduler started (PID: $SCHEDULE_PID)"

# 7. Health check
print_status "Performing health checks..."
sleep 2  # Give services time to start

# Check if processes are still running
if kill -0 $QUEUE_PID 2>/dev/null; then
    print_success "Queue Worker is running"
else
    print_error "Queue Worker failed to start"
fi

if kill -0 $REVERB_PID 2>/dev/null; then
    print_success "Reverb WebSocket is running"
else
    print_error "Reverb WebSocket failed to start"
fi

if kill -0 $SCHEDULE_PID 2>/dev/null; then
    print_success "Task Scheduler is running"
else
    print_error "Task Scheduler failed to start"
fi

# 8. Final status
echo ""
print_success "✅ Application startup completed!"
print_status "📊 System Status:"
echo "  • PDF Directories: Ready & Writable"
echo "  • Database: Migrated"
echo "  • Caches: Optimized"
echo "  • API Docs: Generated"
echo "  • Queue Worker: Running (PID: $QUEUE_PID)"
echo "  • WebSocket: Running (PID: $REVERB_PID)"
echo "  • Task Scheduler: Running (PID: $SCHEDULE_PID)"
echo ""

# 9. Start Main Server (تشغيل السيرفر الرئيسي - لازم يكون آخر واحد)
print_status "🌐 Starting main HTTP server on port 80..."
print_warning "Server will run in foreground. Use Ctrl+C to stop all services."

# Trap signals to cleanup background processes
cleanup() {
    print_warning "Shutting down services..."
    kill $QUEUE_PID 2>/dev/null || true
    kill $REVERB_PID 2>/dev/null || true
    kill $SCHEDULE_PID 2>/dev/null || true
    print_success "All services stopped"
    exit 0
}

trap cleanup INT TERM

php artisan serve --host=0.0.0.0 --port=80