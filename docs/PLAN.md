# Plan: bambamdoku – Open-Source-Vereinssoftware für Schießstände

Referenz für den Funktionsumfang: rangetime.de (öffentliche Seiten, REA-Analyse vom 2026-11-02). Übernommen wird nur, _was_ die Software kann; Name, Texte, Bilder und Code werden nicht übernommen. Produktkontext: [PRODUCT.md](../PRODUCT.md).

## 1. Ziel und Umfang

- **Für wen:** ein Sportschützenverein, der die Software selbst betreibt. Mandanten und Subdomains pro Verein gibt es nicht.
- **MVP:**
    - Standbuchung mit Standaufsicht
    - Digitale Schießkladde des Vereins mit Nachweis-Auszug für das Mitglied
    - Minimale Benutzerverwaltung (Rollen, Gruppen, Einladung per Mail), weil beide Module sie brauchen
- **Nicht vorgesehen:** Stripe und andere Online-Zahlung, Funktionen für kommerzielle Schießanlagen, Mehrmandantenbetrieb

## 2. Stack

Die Vorgabe war ein moderner Stack, der als Prototyp auf dem vorhandenen Hetzner-Webhosting laufen muss. Dort gibt es Apache, PHP, MariaDB und Upload per FTPS, aber keine Prozesse, die dauerhaft laufen.

| Schicht   | Wahl                                                | Warum                                                                                                                       |
| --------- | --------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Backend   | **Laravel 12 (PHP 8.3)**                            | Läuft ohne eigenen Server auf dem Webhosting. Auth, Validierung, Policies, Mails, Queue und Scheduler sind schon eingebaut. |
| Frontend  | **Inertia 2 + Svelte 5 + TypeScript**               | Fühlt sich an wie eine SPA, kommt aber ohne eigene API-Schicht aus. Routen und Rechte bleiben auf dem Server.               |
| Styling   | **Tailwind 4**                                      | Mit eigenen Design-Tokens; fertige Admin-Themes nutzen wir nicht.                                                           |
| Datenbank | **SQLite** für den Prototyp, **MariaDB** im Betrieb | Wechsel über `.env`. Beide sind auf Hetzner verfügbar.                                                                      |
| PDF       | **dompdf** (reines PHP)                             | Für den Nachweis-Auszug aus der Kladde. Braucht kein Chrome auf dem Server.                                                 |
| Tests     | **Pest** (Backend), **Playwright** (E2E)            |                                                                                                                             |
| Lizenz    | Apache-2.0                                          | Liegt schon im Repo.                                                                                                        |

**Einschränkungen des Webhostings und wie wir damit umgehen:**

- Es gibt keine Worker, die dauerhaft laufen. Mails laufen deshalb über `QUEUE_CONNECTION=sync` oder über einen Cron-Aufruf von `schedule:run`, falls das Hosting Cron anbietet.
- Es gibt kein WebSocket. Die Live-Ansicht am Stand fragt deshalb alle 10–15 s neu ab (Inertia-Polling).
- `composer install --no-dev` und `npm run build` laufen lokal. Erst danach lädt `scripts/deploy-hetzner.sh` alles hoch. Der Server führt keinen Build aus.

**Später möglich:** ein Umzug auf einen VPS mit Docker. Erst dann lohnen sich Websockets (Laravel Reverb) und echte Queue-Worker.

## 3. Datenmodell (MVP)

```
users            id, name, email, password, role(admin|member|guest), active
groups           id, name                       ── group_user (n:m)
ranges           id, name, lanes(int), notes    -- Stand, z. B. "25 m Kurzwaffe"
equipment_types  id, name                       -- z. B. Kurzwaffe, Langwaffe, Luftgewehr
range_equipment  range_id, equipment_type_id
shooting_days    id, date, starts_at, ends_at, recurrence_rule?, note,
                 visibility(all|groups|guests)  ── shooting_day_group (n:m)
supervisor_slots id, shooting_day_id, range_id?, user_id, starts_at, ends_at
                 -- range_id null = Aufsicht für die ganze Anlage
bookings         id, shooting_day_id, range_id, lane?, user_id|guest_name,
                 starts_at, ends_at, status(booked|checked_in|cancelled|no_show),
                 booked_by_id
qualifications   user_id, type(range_supervisor), valid_until, proof_note
log_entries      id, user_id|guest_name, shot_on(date), checked_in_at, range_id,
                 weapon_class(short|long|exempt), weapon_owner(own|club),
                 discipline?, booking_id?, confirmed_by_id,
                 source(stand|late_entry)
                 -- Schießkladde des Vereins. weapon_class/weapon_owner tragen § 14:
                 -- Abs. 3 zählt jede erlaubnispflichtige Waffe, Abs. 4 nur die eigene,
                 -- getrennt nach Kurz- und Langwaffe. exempt = erlaubnisfrei, zählt nicht.
users.first_wbk_on  date?                       -- 10-Jahres-Grenze nach § 14 Abs. 4 S. 3
audit_log        id, actor_id, action, subject, payload_json, created_at
```

## 4. Regeln, die der Server durchsetzt

1. **Ohne Aufsicht keine Buchung.** Eine Buchung braucht für ihr gesamtes Zeitfenster eine eingeteilte Standaufsicht, entweder für diesen Stand oder für die ganze Anlage. Wir erzwingen das in einer Policy, nicht nur in der Oberfläche.
2. **Wer Aufsicht führen darf:** nur Benutzer mit gültiger Qualifikation `range_supervisor`.
3. **Kapazität:** Pro Stand und Zeitfenster gilt `count(bookings) ≤ lanes`.
4. **Aufsichtsmodus:** Die eingeteilte Aufsicht darf am aktuellen Schießtag alle Buchungen ihres Standes verwalten (einchecken, Gäste anlegen, stornieren), sonst nichts.
5. **Schießkladde:** Ein Eintrag entsteht am Stand beim Einchecken und trägt die bestätigende Aufsicht. Nachträge (`source=late_entry`) dürfen nur Aufsicht oder Admin anlegen; sie sind gekennzeichnet. Einträge werden nicht gelöscht, sondern storniert. Mitglieder sehen nur ihre eigenen Einträge, Admins alle, weil der Verein gegen die Kladde bescheinigt.
6. **Audit-Log:** Jede Änderung an Buchungen, Aufsichten und Kladde-Einträgen wird protokolliert.
7. **Schwellen nach § 14 WaffG** (Primärquelle: [§ 14 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html)), vom Server je Mitglied berechnet:
    - Abs. 3 (Erwerb): letzte 12 Monate, mit erlaubnispflichtiger Waffe (eigene oder Vereinswaffe), jeden Monat mindestens einmal oder insgesamt mindestens 18-mal.
    - Abs. 4 S. 1–2 (Besitz bis 10 Jahre): letzte 24 Monate, mit eigener Waffe, einmal je drei Monate oder sechsmal in einem abgeschlossenen 12-Monats-Zeitraum; getrennt für Kurz- und Langwaffe, wenn das Mitglied beide besitzt.
    - Abs. 4 S. 3 (ab 10 Jahren nach erster WBK-Eintragung): nur Mitgliedschaft.

## 5. Oberflächen

**Modus:** Alle Oberflächen sind Arbeitsansichten (Modus _Operate_). Das bedeutet klare Hierarchie, vertraute Muster und große Bedienelemente am Stand. Eine Landingpage braucht der Prototyp nicht.

| #   | Screen                                  | Rolle    | Kern-Aufgabe                                                                     |
| --- | --------------------------------------- | -------- | -------------------------------------------------------------------------------- |
| S1  | Login und Einladung annehmen            | alle     | Anmelden, Passwort setzen                                                        |
| S2  | Schießtage                              | alle     | Kommende Tage sehen, mit Aufsicht und freien Bahnen                              |
| S3  | Schießtag-Detail und Buchen             | Mitglied | Zeitfenster-Raster nach Stand; freie Bahn mit 2 Taps buchen                      |
| S4  | Meine Buchungen                         | Mitglied | Buchungen sehen und stornieren                                                   |
| S5  | Aufsicht eintragen                      | Aufsicht | Freie Aufsichtsslots übernehmen                                                  |
| S6  | **Standansicht heute (Aufsichtsmodus)** | Aufsicht | Live-Liste: einchecken, Gast hinzufügen, No-Show; für Tablet optimiert           |
| S7  | Meine Kladde-Einträge                   | Mitglied | Eigene Einträge, Stand der Schwellen nach § 14, PDF-Auszug                       |
| S7a | Admin: Nachweis prüfen                  | Admin    | Je Mitglied Schwellen nach § 14 und Einträge als Grundlage für die Bescheinigung |
| S8  | Admin: Stände und Ausrüstung            | Admin    | Stände, Bahnen, Disziplinen                                                      |
| S9  | Admin: Schießtage                       | Admin    | Termine anlegen (auch wiederkehrend), Freigaben, Aufsicht einteilen              |
| S10 | Admin: Benutzer                         | Admin    | Einladen, Rollen, Gruppen, Qualifikationen                                       |

**Zustände, die im Design vorkommen müssen:**

- Schießtag ohne Aufsicht: Buchen ist gesperrt und die Oberfläche sagt deutlich, warum.
- Ein Stand ist voll.
- Die Buchung liegt in der Vergangenheit.
- Ein Gast hat kein Konto.
- Noch keine Kladde-Einträge.
- Schwelle nach § 14 nicht erreicht (welche, wie viele Termine fehlen).
- PDF für einen Zeitraum ohne Einträge.
- Am Stand ist das Netz schlecht: Wir zeigen, wie alt die Daten sind, und schicken Aktionen erneut ab.

## Stand (2026-11-03)

| Meilenstein              | Status                                                                                                                                                                                                                                                                                                                                                                       |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| M0 Fundament             | erledigt (Commit 94420bc)                                                                                                                                                                                                                                                                                                                                                    |
| M1 Benutzer              | erledigt, PR #1                                                                                                                                                                                                                                                                                                                                                              |
| M2 Stände und Schießtage | erledigt, PR #2                                                                                                                                                                                                                                                                                                                                                              |
| M3 Aufsicht und Buchung  | erledigt, PR #3                                                                                                                                                                                                                                                                                                                                                              |
| M4 Aufsichtsmodus        | erledigt, PR #4                                                                                                                                                                                                                                                                                                                                                              |
| M5 Schießkladde          | erledigt, PR #5                                                                                                                                                                                                                                                                                                                                                              |
| Zusätzlich               | Absage mit Benachrichtigung (#6), Vereinseinstellungen (#7), Protokoll (#8), Erinnerungen und Kalender-Abo (#9), CSV-Import (#10), Terminplanung mit Aufsicht (#12), Aufsicht gesucht (#13), Statistik (#15), Sicherheit (#16), QR-Check-in (#17), App-Icon (#18), Mail-Status (#19), Datensicherung (#20), Fehlerhinweise (#21)                                             |
| Wettbewerbsanalyse       | Betrieb absichern (#24), Aufsichtsregister (#25), Durchsicht-Befunde (#26), Kladde neu mit Eintrags-Tor (#27), Mitglieder ohne Konto (#28), Papier-Ersatzliste (#29); offene Entscheidungen: [ENTSCHEIDUNGEN.md](ENTSCHEIDUNGEN.md)                                                                                                                                          |
| M6 Feldtest (Teil)       | Browser-Tests der Kernabläufe in der CI (#37, #38, #40, #41): Stand, Gast, QR-Ankunft, nicht erschienen, Buchen und Stornieren, Nachtrag, Auszug, Register, Einladung, CSV-Import, Deaktivieren, Handy-Breite; Barrierefreiheit WCAG AA mit axe (#42); schlechtes Netz am Stand (#43); Deploy mit Pflicht-Sicherung und Wartungsseite (#39). Offen: Probelauf mit dem Verein |
| Session-Modell           | Grundlage (#54), Disziplinkatalog wählbar (#55), Bescheinigung (#56), Komfortfunktionen stillgelegt (#57), Mitgliedsantrag und Selbstregistrierung (#58), Session-Kern (#59), Texte, Login-Fix, Barrierefreiheit (#60–#62), Korrektur und Drucke (#63), eigenes Gerät und Kiosk mit PIN (#64); Notizen: [UMBAU-NOTIZEN.md](UMBAU-NOTIZEN.md)                                 |

Abweichungen vom ursprünglichen Plan:

- Die Kladde ist die eigene, insert-only Tabelle `log_entries` (#27). Einträge legt nur eine registrierte Aufsicht an (oder der Admin als Nachtrag mit Grund); Mitglieder tragen nichts selbst ein. Buchungen füllen nur die Eincheckliste vor. **Überholt seit #59:** Mitglieder tragen in einer Schieß-Session selbst ein, und die Aufsicht zeichnet ab, siehe [UMBAU-SESSION.md](UMBAU-SESSION.md).
- Die Qualifikation als Aufsicht steht im Aufsichtsregister `qualifications` (nur anhängen, #25); `users.supervisor_until` ist nur noch abgeleiteter Zwischenspeicher.
- Der Auszug urteilt nicht „erfüllt“, sondern zählt neutral (Empfehlung Q3 = C der Wettbewerbsanalyse).
- Bahnen werden gezählt, nicht einzeln vergeben.

## 6. Meilensteine

| M                            | Inhalt                                                                                                                                                                                   | Fertig, wenn                                                                                                         |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| **M0 Fundament**             | Laravel, Inertia, Svelte und Tailwind aufsetzen. Deploy-Skript auf Laravel umstellen (Docroot `public/`, `.htaccess`, `.env` nicht hochladen). CI mit GitHub Actions: Pest, Lint, Build. | „Hello“ läuft hinter Login auf bambamdoku.irazari-te.ch.                                                             |
| **M1 Benutzer**              | Login, Einladung per Mail, Rollen, Gruppen, Qualifikationen; S1 und S10                                                                                                                  | Der Admin lädt ein, das Mitglied setzt sein Passwort.                                                                |
| **M2 Stände und Schießtage** | S8, S9; Datenmodell, wiederkehrende Termine                                                                                                                                              | Der Admin legt einen Monat Schießtage an.                                                                            |
| **M3 Aufsicht und Buchung**  | S2–S5; Regeln 1–4 als Policies mit Tests                                                                                                                                                 | Ohne Aufsicht lässt sich nicht buchen, und das ist durch Tests belegt.                                               |
| **M4 Aufsichtsmodus**        | S6 mit Polling und Gästen                                                                                                                                                                | Ein Schießtag am Tablet lässt sich komplett abwickeln.                                                               |
| **M5 Kladde und Nachweis**   | S7, S7a, Einträge beim Einchecken, Schwellen nach Regel 7 mit Tests, PDF-Auszug                                                                                                          | Der Schießwart sieht für jedes Mitglied, ob § 14 Abs. 3 bzw. 4 erfüllt ist; das Mitglied lädt seinen Auszug als PDF. |
| **M6 Feldtest**              | Seed mit realistischen Daten, Playwright-E2E, Probelauf mit dem Verein                                                                                                                   | Ein echter Schießtag läuft ohne Papierliste.                                                                         |

**Später, nach Priorität:**

- CSV-Import für Mitglieder und eigene Felder
- Arbeitsdienste
- Inventar und Tresor
- Gebührenverwaltung (ohne Online-Zahlung)
- Kiosk-Terminal mit Anwesenheitsbestätigung
- Erfassung des Munitionsverbrauchs nach BImSchG
- Newsletter
- Kleinanzeigen

## 7. Offene Entscheidungen

- **Format der Bescheinigung:** § 14 WaffG schreibt kein Format vor. Muster des Verbands, bei dem der Verein Mitglied ist, einholen und den PDF-Auszug danach ausrichten.
- **Zeitfenster:** Feste Slots (z. B. 30 oder 60 Minuten) oder freie Zeiten?
- **Gäste:** Braucht ein Gast Name und Adresse, oder reicht der Name?
- **Bahnen:** Werden sie einzeln vergeben oder nur gezählt?
- **Visuelle Richtung:** noch offen. Klären wir mit `/impeccable shape` für S3 und S6, bevor M3 beginnt.
- **Repo-Name und Hosting:** GitHub-Organisation, Domain für den Verein.
