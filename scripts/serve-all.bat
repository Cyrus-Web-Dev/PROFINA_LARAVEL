@echo off
cd /d %~dp0..
start "core"      cmd /k "cd core && php artisan serve --host=127.0.0.1 --port=8000"
start "mkp"       cmd /k "cd modules\mkp && php artisan serve --host=127.0.0.1 --port=8001"
start "sdm"       cmd /k "cd modules\sdm && php artisan serve --host=127.0.0.1 --port=8002"
start "payroll"   cmd /k "cd modules\payroll && php artisan serve --host=127.0.0.1 --port=8003"
start "marketing" cmd /k "cd modules\marketing && php artisan serve --host=127.0.0.1 --port=8004"
