# 🔧 PDF Generation Fix - Business OS

## Problem Description

The employee review PDF export was failing with the error:

```
"Failed to export employee review: PDF generation failed: Please provide a valid cache path."
```

## Root Cause

DomPDF requires specific directories for font caching and temporary files that were missing on the production server:

- `storage/app/fonts/` - Font cache directory
- `storage/fonts/` - Alternative font directory
- `storage/framework/cache/` - Framework cache
- `storage/framework/sessions/` - Session storage
- `storage/framework/views/` - Compiled views

## Solution Implemented

### 1. Created Artisan Command

**File:** `app/Console/Commands/SetupPdfDirectoriesCommand.php`

```bash
php artisan pdf:setup-directories
```

This command:

- Creates all required directories with proper permissions (755)
- Adds `.gitkeep` files to track empty directories
- Tests write permissions
- Provides detailed feedback

### 2. Created PdfService Class

**File:** `app/Services/Reporting/PdfService.php`

Features:

- Automatic directory creation before PDF generation
- Robust error handling with fallback options
- Centralized PDF generation logic
- Comprehensive logging
- Test capabilities

### 3. Updated AdminStatsController

**Changes made:**

- Added `PdfService` dependency injection
- Replaced direct DomPDF calls with `PdfService::downloadPdf()`
- Improved error handling and logging
- Removed redundant directory creation code

### 4. Enhanced Error Handling

- Multiple fallback strategies for PDF generation
- Detailed error logging for debugging
- Graceful degradation when font caching fails

## Quick Fix for Production

### Option 1: Run Artisan Command (Recommended)

```bash
cd erp-backend
php artisan pdf:setup-directories
```

### Option 2: Manual Directory Creation

```bash
cd erp-backend
mkdir -p storage/app/fonts
mkdir -p storage/fonts
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
chmod -R 755 storage/
touch storage/app/fonts/.gitkeep
touch storage/fonts/.gitkeep
```

### Option 3: Use Deployment Script

```bash
chmod +x deploy.sh
./deploy.sh
```

## Testing the Fix

### 1. Test PDF Generation Endpoint

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept-Language: en" \
     "https://your-domain.com/api/v1/admin/stats/employee-review/3/export?dateFrom=2026-01-01&dateTo=2026-01-31"
```

### 2. Check Directory Structure

```bash
ls -la erp-backend/storage/app/fonts/
ls -la erp-backend/storage/fonts/
```

Expected output:

```
drwxr-xr-x 2 www-data www-data 4096 Jan 22 12:00 .
drwxr-xr-x 6 www-data www-data 4096 Jan 22 12:00 ..
-rw-r--r-- 1 www-data www-data    0 Jan 22 12:00 .gitkeep
```

### 3. Verify Write Permissions

```bash
cd erp-backend
touch storage/app/fonts/test.txt && rm storage/app/fonts/test.txt
echo "Write permissions OK"
```

## Code Changes Summary

### New Files Created:

1. `app/Console/Commands/SetupPdfDirectoriesCommand.php` - Directory setup command
2. `app/Services/Reporting/PdfService.php` - Centralized PDF service
3. `deploy.sh` - Automated deployment script
4. `DEPLOYMENT_INSTRUCTIONS.md` - Detailed deployment guide

### Modified Files:

1. `app/Http/Controllers/Api/V1/AdminStatsController.php` - Updated to use PdfService

### Key Improvements:

- **Robustness**: Multiple fallback strategies for PDF generation
- **Maintainability**: Centralized PDF logic in dedicated service
- **Debugging**: Enhanced error logging and reporting
- **Automation**: Artisan command for easy setup
- **Documentation**: Comprehensive deployment instructions

## Prevention for Future Deployments

### 1. Include in Deployment Pipeline

Add to your deployment script:

```bash
php artisan pdf:setup-directories
```

### 2. Docker Configuration

If using Docker, add to Dockerfile:

```dockerfile
RUN mkdir -p storage/app/fonts storage/fonts \
    && chmod -R 755 storage/ \
    && touch storage/app/fonts/.gitkeep storage/fonts/.gitkeep
```

### 3. Infrastructure as Code

Include directory creation in your server provisioning scripts.

## Monitoring

### Check Laravel Logs

```bash
tail -f erp-backend/storage/logs/laravel.log
```

Look for:

- `Created PDF directory: /path/to/directory` (success)
- `PDF generation failed` (errors)
- `PDF generation succeeded with fallback options` (warnings)

### Health Check Endpoint

Consider adding a PDF health check:

```php
Route::get('/health/pdf', function() {
    return app(PdfService::class)->testPdfGeneration();
});
```

## Support

If the issue persists after applying this fix:

1. **Check Permissions**: Ensure `storage/` directory is writable by web server
2. **Check Disk Space**: Ensure sufficient disk space for temporary files
3. **Check PHP Memory**: Increase `memory_limit` if needed
4. **Check Logs**: Review Laravel logs for specific error messages
5. **Test Minimal PDF**: Try generating a simple PDF to isolate the issue

---

**Status**: ✅ **RESOLVED**  
**Applied**: January 22, 2026  
**Tested**: Production server  
**Impact**: PDF export functionality restored
