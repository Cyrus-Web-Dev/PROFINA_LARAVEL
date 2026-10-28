#!/usr/bin/env bash
# Jalankan semua aplikasi PROFINA (development). Hentikan dengan Ctrl+C.
set -e
cd "$(dirname "$0")/.."
pids=()
run() { (cd "$1" && php artisan serve --host=127.0.0.1 --port="$2") & pids+=($!); }
run core 8000; run modules/mkp 8001; run modules/sdm 8002; run modules/payroll 8003; run modules/marketing 8004
trap 'kill "${pids[@]}" 2>/dev/null' EXIT INT TERM
wait
