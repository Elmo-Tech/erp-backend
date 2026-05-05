# 🚀 Deployment Instructions - Business OS (ERP/CRM)

## 📋 Pre-Deployment Checklist

### Server Requirements

- PHP 8.4+
- Composer 2.x
- Node.js 18+ (for frontend)
- MySQL 8.0+ or PostgreSQL 13+
- Redis (for caching and queues)
- Nginx or Apache

### Environment Setup

- SSL Certificate configured
- Domain pointing to server
- Database created and accessible
- Redis server running

## 🔧 Backend Deployment (Laravel 12)

### 1. Code Deployment

```bash
# Clone or pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set proper permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 2. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database, cache, and queue settings in .env
```

### 3. Database Setup

```bash
# Run migrations
php artisan migrate --force

# Seed roles, permissions, dictionaries, and default users
php artisan db:seed
```

### 4. **CRITICAL: PDF Directory Setup**

```bash
# Create required directories for PDF generation
php artisan pdf:setup-directories

# Verify directories were created
ls -la storage/app/fonts/
ls -la storage/fonts/

# If command doesn't exist, create manually:
mkdir -p storage/app/fonts
mkdir -p storage/fonts
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
chmod -R 755 storage/
```

### 5. Cache and Optimization

```bash
# Clear and cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Generate API documentation
php artisan l5-swagger:generate
```

### 6. Queue Worker Setup

```bash
# Install Supervisor
sudo apt install supervisor

# Create supervisor config
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

**Supervisor Configuration:**

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

## 🌐 Frontend Deployment (Next.js)

### 1. Build and Deploy

```bash
# Navigate to frontend directory
cd erp-frontend

# Install dependencies
npm install

# Build for production
npm run build

# Deploy to Vercel (recommended)
vercel --prod

# Or deploy to custom server
npm start
```

### 2. Environment Variables

Create `.env.local` with:

```env
NEXT_PUBLIC_API_URL=https://your-api-domain.com/api/v1
NEXT_PUBLIC_APP_URL=https://your-frontend-domain.com
```

## 🔍 Post-Deployment Verification

### 1. Test PDF Generation

```bash
# Test the problematic endpoint
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept-Language: en" \
     "https://your-domain.com/api/v1/admin/stats/employee-review/1/export?dateFrom=2026-01-01&dateTo=2026-01-31"
```

### 2. Check Logs

```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Check supervisor logs
tail -f storage/logs/worker.log
```

### 3. Verify Directories

```bash
# Ensure PDF directories exist and are writable
ls -la storage/app/fonts/
ls -la storage/fonts/
touch storage/app/fonts/test.txt && rm storage/app/fonts/test.txt
```

## 🚨 Troubleshooting

### PDF Generation Issues

1. **"Please provide a valid cache path"**
    - Run: `php artisan pdf:setup-directories`
    - Check permissions: `chmod -R 755 storage/`

2. **Font loading errors**
    - Verify `storage/app/fonts/` exists and is writable
    - Check DomPDF configuration in `config/dompdf.php`

3. **Memory issues**
    - Increase PHP memory limit: `memory_limit = 512M`
    - Use minimal PDF options in PdfService

### Queue Issues

1. **Jobs not processing**
    - Check supervisor status: `sudo supervisorctl status`
    - Restart workers: `sudo supervisorctl restart laravel-worker:*`

2. **Failed jobs**
    - Check failed jobs: `php artisan queue:failed`
    - Retry failed jobs: `php artisan queue:retry all`

## 📊 Monitoring

### Health Checks

- API endpoint: `GET /api/v1/health`
- Database connectivity
- Queue worker status
- PDF generation test

### Log Monitoring

- Laravel logs: `storage/logs/laravel.log`
- Nginx/Apache access logs
- Error logs for 500 errors

## 🔐 Security Checklist

- [ ] SSL certificate installed and working
- [ ] Environment variables secured (no .env in public)
- [ ] Database credentials secured
- [ ] API rate limiting configured
- [ ] CORS settings properly configured
- [ ] File upload restrictions in place
- [ ] PDF generation sandboxed (no PHP execution)

## 📞 Support

If issues persist:

1. Check Laravel logs for detailed error messages
2. Verify all directories have proper permissions
3. Test PDF generation with minimal data
4. Contact development team with specific error messages

---

**Last Updated:** January 22, 2026  
**Version:** 1.0.0
