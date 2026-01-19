#!/bin/bash

# Start Laravel Queue Worker
# This script should be run in the background or via supervisor

echo "Starting Laravel Queue Worker..."
php artisan queue:work --verbose --tries=3 --timeout=90
