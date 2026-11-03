#!/usr/bin/env bash
# bambamdoku auf Hetzner-Webhosting bringen (bambamdoku.irazari-te.ch).
#
# Aufteilung auf dem Server (Pfade relativ zum FTP-Home):
#   bambamdoku-app/          Laravel-Code, vendor/, storage/, .env  (nicht im Web erreichbar)
#   public_html/bambamdoku/  nur der Inhalt von public/ (Docroot der Subdomain)
#
# Gebaut wird lokal in .deploy/ (composer --no-dev, npm run build), der Server
# fuehrt keinen Build aus. mirror --delete wirkt nur in diesen zwei Verzeichnissen.
# .env, Logs, Sessions, Cache, Sicherungen und die SQLite-Datenbank auf dem
# Server bleiben unberuehrt.
#
# Passwort kommt aus dem macOS-Schluesselbund. Einmalig ablegen
# (Passwort vorher in konsoleH -> Logindaten -> FTP-Hauptbenutzer kopieren):
#   security add-generic-password -U -s hetzner-irazari-ftp -a ebrh7y -w "$(pbpaste)"
#
# Optional: HETZNER_SSH=user@host setzen, dann laufen danach per SSH
# migrate und config:cache. Ohne SSH siehe docs/DEPLOY.md.
set -euo pipefail
cd "$(dirname "$0")/.."

FTP_USER=ebrh7y
FTP_HOST=www795.your-server.de
APP_DIR=bambamdoku-app
# Absoluter Pfad, wie PHP ihn sieht. Der Webserver sieht public_html unter
# /usr/www/users/<login>/, ein relativer Pfad ../.. landet daher nicht im Home.
APP_ROOT=/usr/home/$FTP_USER/$APP_DIR
WEB_DIR=public_html/bambamdoku

PW="$(security find-generic-password -s hetzner-irazari-ftp -a "$FTP_USER" -w 2>/dev/null)" || {
  echo "Kein Passwort im Schlüsselbund. Einmalig ablegen:" >&2
  echo "  security add-generic-password -U -s hetzner-irazari-ftp -a $FTP_USER -w \"\$(pbpaste)\"" >&2
  exit 1; }

# 0. Nur geprüfte Stände ausliefern: HEAD muss origin/main sein und dessen CI grün.
#    Notfall-Ausnahme (bewusst laut): DEPLOY_OHNE_CI=1 scripts/deploy-hetzner.sh
if [[ "${DEPLOY_OHNE_CI:-}" != "1" ]]; then
  git fetch -q origin main
  HEAD_SHA="$(git rev-parse HEAD)"
  if [[ "$HEAD_SHA" != "$(git rev-parse origin/main)" ]]; then
    echo "Abbruch: Ausgecheckt ist nicht origin/main. Erst mergen, dann deployen." >&2
    exit 1
  fi
  # Auch ungetrackte Dateien: der Build bündelt alle Seiten unter resources/js/pages.
  if [[ -n "$(git status --porcelain -- . ':!.agents' ':!.claude')" ]]; then
    echo "Abbruch: Es gibt uncommittete oder ungetrackte Dateien." >&2
    exit 1
  fi
  CI="$(gh run list --commit "$HEAD_SHA" --workflow tests --json conclusion -q '.[0].conclusion' 2>/dev/null || true)"
  if [[ "$CI" != "success" ]]; then
    echo "Abbruch: CI für ${HEAD_SHA:0:7} ist nicht grün (Status: ${CI:-unbekannt})." >&2
    exit 1
  fi
else
  echo "WARNUNG: Deploy ohne CI-Prüfung." >&2
fi

# 1. Sauberen Build-Stand zusammenstellen
echo "==> Build"
npm run build
rm -rf .deploy && mkdir .deploy
# Quelle ist genau der Commit (git archive), nicht das Arbeitsverzeichnis; dazu nur das gebaute Frontend.
git archive HEAD | tar -x -C .deploy
rm -rf .deploy/tests .deploy/.github
rsync -a public/build/ .deploy/public/build/
(cd .deploy && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

# index.php zeigt aus dem Docroot auf das App-Verzeichnis ausserhalb von public_html
cat > .deploy/public/index.php <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = '__APP_ROOT__';

if (file_exists($maintenance = $root.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $root.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $root.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
PHP
sed -i '' "s#__APP_ROOT__#$APP_ROOT#" .deploy/public/index.php

# 2. Hochladen
echo "==> Upload"
EXCLUDES=(--exclude-glob .env --exclude-glob 'database/*.sqlite*'
  --exclude-glob 'storage/logs/*.log' --exclude-glob 'storage/framework/sessions/*'
  --exclude-glob 'storage/framework/views/*.php' --exclude-glob 'storage/framework/cache/data/*'
  --exclude-glob 'storage/framework/maintenance.php' --exclude-glob 'storage/app/private/*' --exclude-glob public/)

LFTP_PASSWORD="$PW" lftp --env-password -u "$FTP_USER" "$FTP_HOST" -e "
set cmd:fail-exit true; set dns:order inet; set net:timeout 15; set net:max-retries 1;
set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate true;
mkdir -pf $APP_DIR;
mirror --reverse --delete --parallel=4 ${EXCLUDES[*]} .deploy $APP_DIR;
mirror --reverse --delete --parallel=4 --exclude-glob .well-known/ .deploy/public $WEB_DIR;
quit"

# 3. Optional: Migrationen per SSH
if [[ -n "${HETZNER_SSH:-}" ]]; then
  echo "==> Migrate"
  ssh "$HETZNER_SSH" "cd $APP_DIR && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache"
fi

echo "Fertig: https://bambamdoku.irazari-te.ch"
