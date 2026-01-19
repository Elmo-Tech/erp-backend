#!/bin/bash

# 1. Fix Permissions (عشان لارفيل يعرف يكتب)
chown -R 33:33 /app/storage
chmod -R 775 /app/storage

# 2. Laravel Setup (تجهيز المشروع)
php artisan storage:link
php artisan migrate --force
php artisan optimize

# 3. Start Background Processes (تشغيل الخدمات الخلفية)
# تشغيل الـ Queue Worker في الخلفية
php artisan queue:work --tries=3 --timeout=90 &

# تشغيل الـ Reverb (WebSocket) في الخلفية
php artisan reverb:start --host=0.0.0.0 --port=8080 &

# 4. Start Main Server (تشغيل السيرفر الرئيسي - لازم يكون آخر واحد)
php artisan serve --host=0.0.0.0 --port=80