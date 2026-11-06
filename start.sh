#!/bin/bash
cd "$(dirname "$0")"

# Check if PHP is installed
if ! command -v php >/dev/null 2>&1; then
  echo "PHP is not installed."
  echo "On Ubuntu/Debian: sudo apt update && sudo apt install -y php php-sqlite3"
  echo "On Fedora: sudo dnf install php php-pdo php-sqlite3"
  echo "On Arch Linux: sudo pacman -S php php-sqlite"
  exit 1
fi

# Check if PDO SQLite extension is enabled
if ! php -m | grep -qi '^pdo_sqlite$'; then
  echo "The PDO SQLite extension is missing from this PHP installation."
  echo "Please install/enable php-sqlite3 or pdo_sqlite in php.ini, then try again."
  exit 1
fi

if [ ! -f "storage/labtrace.db" ]; then
  echo "The database will be created automatically on first launch."
fi

URL="http://127.0.0.1:8000"
(xdg-open "$URL" >/dev/null 2>&1 || sensible-browser "$URL" >/dev/null 2>&1 || true) &

echo "============================================="
echo "LabTrace is running at $URL"
echo "Press Ctrl+C to stop the server."
echo "============================================="

php -S 127.0.0.1:8000

