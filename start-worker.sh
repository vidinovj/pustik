#!/bin/bash
cd "$(dirname "$0")"
nohup php artisan queue:work --timeout=0 > storage/logs/queue-worker.log 2>&1 &
echo "Queue worker started with nohup. Check storage/logs/queue-worker.log for output."