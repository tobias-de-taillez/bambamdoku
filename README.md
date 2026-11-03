# bambamdoku

Freie Software für Sportschützenvereine: Schießtage planen, Bahnen buchen, Standaufsicht sicherstellen und die Schießkladde für den Bedürfnisnachweis führen. Für einen Verein, der seine Daten selbst behält – läuft auf günstigem Webhosting.

## Was es kann

**Für Mitglieder**

- Schießtage ansehen und eine Bahn in zwei Klicks buchen; Raster zeigt freie Bahnen je Stand und Zeit
- Gäste mitbringen, Vereinswaffe angeben, selbst stornieren (bis zur Stornofrist)
- Meine Buchungen, Erinnerung am Vortag, Kalender-Abo fürs Handy
- am Stand per **QR-Code** selbst anmelden; lässt sich wie eine App auf den Home-Bildschirm legen
- **Schießkladde**: die eigenen, von der Aufsicht eingetragenen Termine mit Anzahl Tage je Kategorie (alle / eigene Waffe) und Auszug zur Information – bewusst ohne Urteil „erfüllt“, bescheinigen muss der Verband

**Für Standaufsichten**

- **Am Stand eintragen**: Mitglied per Name oder Mitgliedsnummer suchen, Kategorie und Waffe sind vorbelegt, grüne Bestätigung – in Sekunden, ohne Buchung und ohne Konto des Mitglieds
- Das Tor sitzt am Eintrag: nur mit gültiger Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV), nie für sich selbst, Datum vom Server, Kurz-/Langwaffe nur mit passendem Umfang
- Einträge werden nie bearbeitet oder gelöscht, nur mit Grund storniert; doppeltes Tippen erzeugt keinen zweiten Eintrag
- Aufsicht selbst übernehmen; **ohne eingeteilte Aufsicht keine Buchung** (§ 27 WaffG)
- Standansicht am Schießtag: Anwesenheit bestätigen, nicht erschienen, Schützen und Gäste spontan eintragen; aktualisiert sich selbst
- QR-Code für den Selbst-Check-in (wechselt alle 5 Minuten); Mitglieder erscheinen als „ist da“, die Aufsicht bestätigt
- Mail „Aufsicht gesucht“ drei Tage vorher, wenn noch niemand eingetragen ist; Hinweis vor Ablauf der eigenen Qualifikation

**Für den Vorstand**

- Mitglieder anlegen (auch ohne E-Mail und Konto), per CSV importieren, per Link einladen; Rollen, Mitgliedsnummer, Eintritt
- **Aufsichtsregister** nach § 10 Abs. 3 AWaffV: jede Prüfung eine neue Zeile mit Umfang, gültig bis, geprüft am und von
- Stände mit Bahnen und zugelassenen Waffenarten; Schießtage einzeln oder wöchentlich wiederholt angelegt; Schalter „Gastschützen erlaubt“ für die Online-Buchung
- Absage storniert alle Buchungen und benachrichtigt die Betroffenen
- Schießkladde des Vereins inkl. Gäste und Stornos, CSV-Export, **Nachträge** mit Pflichtgrund, **Auszug zur Vorlage beim Verband** je Mitglied, Papier-Ersatzliste, Mitgliedschaftsbescheinigung (§ 14 Abs. 4 Satz 3)
- Protokoll aller Buchungen, Check-ins und Änderungen
- Jahresstatistik: Besuche je Monat, Auslastung je Stand, aktivste Mitglieder, Aufsichtsdienste
- Vereinseinstellungen: Name, Anschrift, Buchungsraster, Stornofrist; Mail-Status mit Testmail; nächtliche Datensicherung zum Herunterladen

## Technik

Laravel 13 (PHP ≥ 8.3), Inertia, Svelte 5, TypeScript, Tailwind 4. SQLite oder MariaDB. Keine Dauerprozesse nötig: ein Cronjob pro Minute (`cron.php`) reicht für Migrationen, Erinnerungen (17 Uhr), „Aufsicht gesucht“ (18 Uhr) und die Datensicherung (3:30 Uhr).

Die Fachregeln liegen gebündelt in `app/Domain`: `Logbook` (Kladde und Eintrags-Tor), `Bookings` und `Schedule` (Buchung und Aufsicht), `ShootingRecord`, `TimeWindow`. Die rechtlichen Invarianten der Kladde prüft `tests/Feature/LogbookTest.php`.

Fachliche Grundlage und offene Entscheidungen: [docs/ENTSCHEIDUNGEN.md](docs/ENTSCHEIDUNGEN.md).

## Lokal ausprobieren

```bash
composer setup                              # Abhängigkeiten, .env, SQLite, Frontend-Build
php artisan db:seed --class=DemoSeeder      # Beispielverein mit zwei Jahren Historie
composer dev                                # http://localhost:8000
```

Demo-Konten: `petra.vorstand@demo.test` (Admin, Aufsicht), `helga.schiesswart@demo.test` (Aufsicht), `max.mustermann@demo.test` (Mitglied) – Passwort jeweils `demo-passwort`.

Ohne Demo: `ADMIN_EMAIL` und `ADMIN_PASSWORD` in `.env` setzen und `php artisan db:seed` ausführen. Die öffentliche Registrierung ist abgeschaltet; weitere Mitglieder lädt der Vorstand ein.

## Prüfen

```bash
composer ci:check     # Formatierung, svelte-check, Pint, PHPStan, PHPUnit
```

Browser-Tests der Kernabläufe (Eintragen am Stand, Buchen, Nachtrag, Auszug) gegen den Demo-Verein:

```bash
npx playwright install chromium   # einmalig
npm run build && npm run test:e2e
```

## Betrieb

- Übergabe und erste Schritte: [docs/UEBERGABE.md](docs/UEBERGABE.md)
- Shared Hosting (Hetzner o. ä.): [docs/DEPLOY.md](docs/DEPLOY.md)
- Konfiguration: `.env` (`VEREIN_*`) oder in der Oberfläche unter _Verwaltung → Einstellungen_
- Mailversand: SMTP in `.env` (`MAIL_*`); ohne SMTP landen Mails im Log

## Rechtliches

Die Auswertung nach § 14 WaffG ist eine Orientierung. Maßgeblich bleibt die Bescheinigung des Schießsportverbands. Rechtsgrundlagen: [§ 14 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html), [§ 27 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__27.html), [§§ 10, 11 AWaffV](https://www.gesetze-im-internet.de/awaffv/).

## Lizenz

[Apache License 2.0](LICENSE)
