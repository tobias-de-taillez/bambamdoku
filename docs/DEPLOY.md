# Deploy auf Hetzner-Webhosting

`scripts/deploy-hetzner.sh` baut lokal und lädt per FTPS hoch. Auf dem Server läuft kein Build.

| Server-Pfad (FTP-Home)    | Inhalt                                                                                              |
| ------------------------- | --------------------------------------------------------------------------------------------------- |
| `bambamdoku-app/`         | Laravel-Code, `vendor/`, `storage/`, `.env`, ggf. SQLite-Datei. Nicht im Web erreichbar.            |
| `public_html/bambamdoku/` | Nur `public/` (Docroot von bambamdoku.irazari-te.ch). `index.php` zeigt auf `../../bambamdoku-app`. |

## Einmalig einrichten

1. In konsoleH für die Domain **PHP 8.3 oder neuer** einstellen.
2. FTP-Passwort im Schlüsselbund ablegen (siehe Kopf des Skripts).
3. Erstes Deploy ausführen: `scripts/deploy-hetzner.sh`.
4. `bambamdoku-app/.env` auf dem Server anlegen (aus `.env.example`), mindestens:
    - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://bambamdoku.irazari-te.ch`
    - `APP_KEY=` mit einem Wert aus `php artisan key:generate --show` (lokal erzeugen)
    - Datenbank: entweder `DB_CONNECTION=sqlite` mit `DB_DATABASE=/absoluter/pfad/bambamdoku-app/database/database.sqlite` oder die MariaDB-Zugangsdaten aus konsoleH
    - `MAIL_*` für den Versand, `ADMIN_EMAIL` / `ADMIN_PASSWORD` für den ersten Admin
5. Datenbank anlegen und Admin seeden:
    - **Mit SSH:** `cd bambamdoku-app && php artisan migrate --force && php artisan db:seed --force`
    - **Ohne SSH:** offen, siehe unten.
6. `storage/` und `bootstrap/cache/` müssen für PHP beschreibbar sein.

## Jedes weitere Deploy

```bash
scripts/deploy-hetzner.sh                       # nur Upload
HETZNER_SSH=user@host scripts/deploy-hetzner.sh # Upload + migrate + Caches
```

`.env`, Logs, Sessions, Cache und `database/*.sqlite` auf dem Server werden nie überschrieben oder gelöscht.

## Offen

- Ob der Hetzner-Tarif SSH hat. Ohne SSH bräuchte es für Migrationen einen geschützten Web-Endpunkt oder einen Cron-Job; das ist noch nicht gebaut.
