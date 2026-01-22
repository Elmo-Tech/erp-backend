# Arabic PDF Encoding Fix

## Problem

Arabic text displays as question marks (?????) in generated PDFs due to font encoding issues.

## Root Cause

1. **Google Fonts not accessible**: dompdf cannot access external font imports
2. **Limited Arabic glyph support**: Default fonts lack proper Arabic character support
3. **Font subsetting**: May remove Arabic characters during PDF generation

## Solution Overview

### 1. Font Management

- Download proper Arabic fonts (Noto Sans Arabic)
- Install fonts locally in `storage/app/fonts/`
- Configure dompdf to use local fonts only

### 2. Configuration Updates

- Disable font subsetting for Arabic characters
- Set proper default fonts based on locale
- Configure secure font directories

### 3. Template Updates

- Remove Google Fonts imports from PDF templates
- Use local font stack with proper fallbacks

## Implementation Steps

### Quick Fix (Automated)

```bash
cd erp-backend
./fix-arabic-pdf.sh
```

### Manual Setup

#### 1. Setup Directories

```bash
php artisan pdf:setup-directories
```

#### 2. Install Arabic Fonts

```bash
# Download and install fonts automatically
php artisan pdf:install-fonts --download

# Or install existing fonts
php artisan pdf:install-fonts
```

#### 3. Test Arabic PDF Generation

```bash
php artisan pdf:test-arabic --save
```

## Font Configuration

### Supported Fonts

- **Noto Sans Arabic Regular**: Primary Arabic font
- **Noto Sans Arabic Bold**: Bold Arabic text
- **DejaVu Sans**: Fallback font with Unicode support

### Font Stack

```css
/* Arabic */
font-family: "Noto Sans Arabic", "DejaVu Sans", "Arial Unicode MS", sans-serif;

/* English */
font-family: "DejaVu Sans", "Arial", sans-serif;
```

## Key Configuration Changes

### dompdf.php

```php
'default_font' => 'DejaVu Sans',
'enable_font_subsetting' => false, // Critical for Arabic
'font_dir' => storage_path('app/fonts'),
'font_cache' => storage_path('app/fonts'),
```

### PDF Service Updates

- Dynamic font selection based on locale
- Proper font configuration loading
- Enhanced error handling with fallbacks

### Template Updates

- Removed external font imports
- Local font stack implementation
- Proper RTL/LTR direction handling

## Testing

### Automated Test

```bash
php artisan pdf:test-arabic
```

### Manual Test

```php
$pdfService = app(PdfService::class);
$pdf = $pdfService->generatePdf('reports.employee-review', [
    'locale' => 'ar',
    'employee' => ['name' => 'أحمد محمد']
]);
```

## Troubleshooting

### Common Issues

#### 1. Fonts Not Found

**Symptoms**: Still showing question marks
**Solution**:

```bash
php artisan pdf:install-fonts --download
ls -la storage/app/fonts/
```

#### 2. Permission Errors

**Symptoms**: Cannot write to font directory
**Solution**:

```bash
chmod -R 755 storage/app/fonts
chown -R www-data:www-data storage/app/fonts
```

#### 3. Font Subsetting Issues

**Symptoms**: Some Arabic characters missing
**Solution**: Ensure `enable_font_subsetting` is `false`

#### 4. Template Errors

**Symptoms**: PDF generation fails
**Solution**: Check for external font imports in templates

### Debug Commands

```bash
# Check font availability
ls -la storage/app/fonts/

# Test PDF generation
php artisan pdf:test-arabic --save

# Check permissions
ls -la storage/app/

# Clear caches
php artisan config:clear
php artisan view:clear
```

## File Structure

```
storage/app/fonts/
├── NotoSansArabic-Regular.ttf
├── NotoSansArabic-Bold.ttf
├── font_config.php
└── .gitkeep

app/Console/Commands/
├── SetupPdfDirectoriesCommand.php
├── InstallPdfFontsCommand.php
└── TestArabicPdfCommand.php

app/Services/Reporting/
└── PdfService.php (updated)

resources/views/reports/
└── employee-review.blade.php (updated)
```

## Verification

After implementing the fix:

1. ✅ Arabic text displays correctly in PDFs
2. ✅ Font fallbacks work properly
3. ✅ No external font dependencies
4. ✅ Secure font directory configuration
5. ✅ Both Arabic and English locales supported

## Maintenance

### Adding New Fonts

1. Download font files (.ttf)
2. Place in `storage/app/fonts/`
3. Update font configuration
4. Test with `php artisan pdf:test-arabic`

### Updating Templates

- Always use local font stacks
- Test with Arabic content
- Verify RTL/LTR direction handling

## Security Notes

- Font directory restricted to storage path
- External font access disabled
- No remote font loading
- Proper file permissions set
