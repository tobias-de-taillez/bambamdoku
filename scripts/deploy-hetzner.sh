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

# 1. Sauberen Build-Stand zusammenstellen (vor der Wartung, damit sie kurz bleibt)
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

# 2. Wartungsseite an, dann die Produktions-Datenbank sichern und prüfen.
#    Während der Wartung schreibt niemand (auch cron.php pausiert), die Kopie ist
#    also in sich stimmig. Liegt ausserhalb von .deploy/, das oben neu angelegt wird.
#    Nur fuer die allererste Installation: DEPLOY_OHNE_SICHERUNG=1
BACKUP_DIR="${BAMBAMDOKU_BACKUPS:-$HOME/bambamdoku-backups}"
lftp_run() {
  LFTP_PASSWORD="$PW" lftp --env-password -u "$FTP_USER" "$FTP_HOST" -e "
  set cmd:fail-exit true; set dns:order inet; set net:timeout 15; set net:max-retries 1;
  set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate true;
  $1
  quit"
}

echo "==> Wartungsseite an"
lftp_run "mkdir -pf $APP_DIR/storage/framework; rm -f $APP_DIR/storage/framework/deploy-fertig; put scripts/wartung.php -o $APP_DIR/storage/framework/maintenance.php;"
# Laufende Anfragen und einen gerade laufenden Cronjob ausklingen lassen.
sleep 5

if [[ "${DEPLOY_OHNE_SICHERUNG:-}" != "1" ]]; then
  echo "==> Sicherung"
  mkdir -p "$BACKUP_DIR"
  BACKUP="$BACKUP_DIR/prod-$(date +%Y%m%d-%H%M%S).sqlite"
  lftp_run "get $APP_DIR/database/database.sqlite -o $BACKUP;" || true
  if [[ ! -s "$BACKUP" ]] || [[ "$(sqlite3 "$BACKUP" 'pragma integrity_check;')" != "ok" ]]; then
    echo "Abbruch: Sicherung $BACKUP fehlt, ist leer oder beschädigt. Nichts hochgeladen." >&2
    lftp_run "rm $APP_DIR/storage/framework/maintenance.php;" || echo "Wartungsseite konnte nicht entfernt werden!" >&2
    exit 1
  fi
  echo "    $BACKUP ($(du -h "$BACKUP" | cut -f1), Integrität ok)"
  # Die letzten 30 Sicherungen behalten
  ls -1t "$BACKUP_DIR"/prod-*.sqlite | tail -n +31 | while read -r old; do rm -f "$old"; done
else
  echo "WARNUNG: Deploy ohne Sicherung der Produktions-Datenbank." >&2
fi

# 3. Hochladen. Danach migriert cron.php in der nächsten Minute und schaltet
#    erst dann die Wartungsseite ab – neuer Code läuft nie gegen das alte Schema.
echo "==> Upload"
EXCLUDES=(--exclude-glob .env --exclude-glob 'database/*.sqlite*'
  --exclude-glob 'storage/logs/*.log' --exclude-glob 'storage/framework/sessions/*'
  --exclude-glob 'storage/framework/views/*.php' --exclude-glob 'storage/framework/cache/data/*'
  --exclude-glob 'storage/framework/maintenance.php' --exclude-glob 'storage/app/private/*' --exclude-glob public/)

MARKER="$(mktemp)"
lftp_run "
mirror --reverse --delete --parallel=4 ${EXCLUDES[*]} --exclude-glob storage/framework/deploy-fertig .deploy $APP_DIR;
mirror --reverse --delete --parallel=4 --exclude-glob .well-known/ .deploy/public $WEB_DIR;
put $MARKER -o $APP_DIR/storage/framework/deploy-fertig;" || {
  echo "Abbruch beim Upload. Die Seite zeigt weiter „Kurze Wartung“, bis ein Deploy durchläuft." >&2
  exit 1; }
echo "    hochgeladen; cron.php migriert und beendet die Wartung in der nächsten Minute"

# 4. Optional: Migrationen per SSH
if [[ -n "${HETZNER_SSH:-}" ]]; then
  echo "==> Migrate"
  ssh "$HETZNER_SSH" "cd $APP_DIR && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache"
fi

# 5. Warten, bis cron.php migriert und die Wartung beendet hat
echo "==> Warten auf Migration und Ende der Wartung"
for _ in $(seq 1 30); do
  if [[ "$(curl -s -o /dev/null -w '%{http_code}' https://bambamdoku.irazari-te.ch/up)" == "200" ]]; then
    echo "Fertig: https://bambamdoku.irazari-te.ch"
    exit 0
  fi
  sleep 5
done
echo "ACHTUNG: Nach 150 s noch Wartungsseite. Läuft der Cronjob? Schlägt die Migration fehl? storage/logs/laravel.log prüfen." >&2
exit 1
