#!/usr/bin/env bash
#
# Prepare a dedicated demo database for the screenshot/video pipeline.
# This never touches your normal development database.
#
#   bash marketing/video/prepare-demo.sh
#
set -euo pipefail

cd "$(dirname "$0")/../.." # acadflow-web

DB_FILE="$(pwd)/database/marketing-demo.sqlite"
touch "$DB_FILE"

echo "Migrating + seeding demo database: $DB_FILE"
DB_DATABASE="$DB_FILE" DB_CONNECTION=sqlite php artisan migrate:fresh --force
DB_DATABASE="$DB_FILE" DB_CONNECTION=sqlite php artisan db:seed --class=MarketingDemoSeeder --force

cat <<EOF

Demo database ready.

Start the demo app in another terminal:

  DB_DATABASE="$DB_FILE" DB_CONNECTION=sqlite php artisan serve --host=127.0.0.1 --port=8123

Then capture:

  npm run video:capture
  npm run video:clean
EOF
