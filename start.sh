#!/usr/bin/env bash
set -e
cd "$(dirname "$0")"

if [ ! -f .env ]; then
  echo "HATA: .env dosyası yok. Önce: cp .env.example .env && php artisan key:generate"
  exit 1
fi

if ! grep -qE '^BELSIS_PASSWORD=.+' .env; then
  echo "HATA: .env içinde BELSIS_PASSWORD boş."
  echo "Şunu ekleyin: BELSIS_PASSWORD=ada11sql"
  exit 1
fi

php artisan config:clear >/dev/null

PORT="${1:-8000}"
if lsof -i ":$PORT" >/dev/null 2>&1; then
  PORT=8001
  echo "Port 8000 meşgul, $PORT kullanılıyor."
fi

echo "Belediye Kiosk → http://127.0.0.1:$PORT"
echo "Demo sicil: 89874"
php artisan serve --host=127.0.0.1 --port="$PORT"
