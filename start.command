#!/bin/bash
cd "$(dirname "$0")"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP is not installed. Install it with: brew install php"
  read -r -p "Press Enter to close..."
  exit 1
fi

if ! php -m | grep -qi '^pdo_sqlite$'; then
  echo "The PDO SQLite extension is missing from this PHP installation."
  echo "Install/enable a PHP build that includes pdo_sqlite, then try again."
  read -r -p "Press Enter to close..."
  exit 1
fi

if [ ! -f "storage/labtrace.db" ]; then
  echo "The database will be created automatically on first launch."
fi

URL="http://127.0.0.1:8000"
(open "$URL" >/dev/null 2>&1 || true)
echo "LabTrace is running at $URL"
echo "Keep this Terminal window open while using the website."
echo "Press Control+C to stop the server."
php -S 127.0.0.1:8000
