# Plan: bambamdoku – Open-Source-Vereinssoftware für Schießstände

Referenz für den Funktionsumfang: rangetime.de (öffentliche Seiten, REA-Analyse vom 2026-11-02). Übernommen wird nur, _was_ die Software kann; Name, Texte, Bilder und Code werden nicht übernommen. Produktkontext: [PRODUCT.md](../PRODUCT.md).

## 1. Ziel und Umfang

- **Für wen:** ein Sportschützenverein, der die Software selbst betreibt. Mandanten und Subdomains pro Verein gibt es nicht.
- **MVP:**
    - Standbuchung mit Standaufsicht
    - Digitales Schießbuch
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
| PDF       | **dompdf** (reines PHP)                             | Für den Schießbuch-Nachweis. Braucht kein Chrome auf dem Server.                                                            |
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
diary_entries    id, user_id, date, range_name, discipline, equipment_type_id,
                 shots?, note, source(auto|manual), booking_id?, confirmed_by_id?
audit_log        id, actor_id, action, subject, payload_json, created_at
```

## 4. Regeln, die der Server durchsetzt

1. **Ohne Aufsicht keine Buchung.** Eine Buchung braucht für ihr gesamtes Zeitfenster eine eingeteilte Standaufsicht, entweder für diesen Stand oder für die ganze Anlage. Wir erzwingen das in einer Policy, nicht nur in der Oberfläche.
2. **Wer Aufsicht führen darf:** nur Benutzer mit gültiger Qualifikation `range_supervisor`.
3. **Kapazität:** Pro Stand und Zeitfenster gilt `count(bookings) ≤ lanes`.
4. **Aufsichtsmodus:** Die eingeteilte Aufsicht darf am aktuellen Schießtag alle Buchungen ihres Standes verwalten (einchecken, Gäste anlegen, stornieren), sonst nichts.
5. **Schießbuch:** Wenn eine Buchung eingecheckt ist, entsteht ein Eintrag mit `source=auto` und dem Verweis auf die Aufsicht. Manuelle Einträge sind erlaubt und als solche gekennzeichnet. Auch Administratoren sehen keine fremden Einträge.
6. **Audit-Log:** Jede Änderung an Buchungen, Aufsichten und Schießbuch-Einträgen wird protokolliert.

## 5. Oberflächen

**Modus:** Alle Oberflächen sind Arbeitsansichten (Modus _Operate_). Das bedeutet klare Hierarchie, vertraute Muster und große Bedienelemente am Stand. Eine Landingpage braucht der Prototyp nicht.

| #   | Screen                                  | Rolle    | Kern-Aufgabe                                                           |
| --- | --------------------------------------- | -------- | ---------------------------------------------------------------------- |
| S1  | Login und Einladung annehmen            | alle     | Anmelden, Passwort setzen                                              |
| S2  | Schießtage                              | alle     | Kommende Tage sehen, mit Aufsicht und freien Bahnen                    |
| S3  | Schießtag-Detail und Buchen             | Mitglied | Zeitfenster-Raster nach Stand; freie Bahn mit 2 Taps buchen            |
| S4  | Meine Buchungen                         | Mitglied | Buchungen sehen und stornieren                                         |
| S5  | Aufsicht eintragen                      | Aufsicht | Freie Aufsichtsslots übernehmen                                        |
| S6  | **Standansicht heute (Aufsichtsmodus)** | Aufsicht | Live-Liste: einchecken, Gast hinzufügen, No-Show; für Tablet optimiert |
| S7  | Schießbuch                              | Mitglied | Einträge, manueller Eintrag, Filter nach Zeitraum, PDF-Nachweis        |
| S8  | Admin: Stände und Ausrüstung            | Admin    | Stände, Bahnen, Disziplinen                                            |
| S9  | Admin: Schießtage                       | Admin    | Termine anlegen (auch wiederkehrend), Freigaben, Aufsicht einteilen    |
| S10 | Admin: Benutzer                         | Admin    | Einladen, Rollen, Gruppen, Qualifikationen                             |

**Zustände, die im Design vorkommen müssen:**

- Schießtag ohne Aufsicht: Buchen ist gesperrt und die Oberfläche sagt deutlich, warum.
- Ein Stand ist voll.
- Die Buchung liegt in der Vergangenheit.
- Ein Gast hat kein Konto.
- Leeres Schießbuch.
- PDF für einen Zeitraum ohne Einträge.
- Am Stand ist das Netz schlecht: Wir zeigen, wie alt die Daten sind, und schicken Aktionen erneut ab.

## 6. Meilensteine

| M                            | Inhalt                                                                                                                                                                                   | Fertig, wenn                                                           |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| **M0 Fundament**             | Laravel, Inertia, Svelte und Tailwind aufsetzen. Deploy-Skript auf Laravel umstellen (Docroot `public/`, `.htaccess`, `.env` nicht hochladen). CI mit GitHub Actions: Pest, Lint, Build. | „Hello“ läuft hinter Login auf bambamdoku.irazari-te.ch.               |
| **M1 Benutzer**              | Login, Einladung per Mail, Rollen, Gruppen, Qualifikationen; S1 und S10                                                                                                                  | Der Admin lädt ein, das Mitglied setzt sein Passwort.                  |
| **M2 Stände und Schießtage** | S8, S9; Datenmodell, wiederkehrende Termine                                                                                                                                              | Der Admin legt einen Monat Schießtage an.                              |
| **M3 Aufsicht und Buchung**  | S2–S5; Regeln 1–4 als Policies mit Tests                                                                                                                                                 | Ohne Aufsicht lässt sich nicht buchen, und das ist durch Tests belegt. |
| **M4 Aufsichtsmodus**        | S6 mit Polling und Gästen                                                                                                                                                                | Ein Schießtag am Tablet lässt sich komplett abwickeln.                 |
| **M5 Schießbuch**            | S7, Auto-Einträge, PDF                                                                                                                                                                   | Ein Mitglied lädt seinen Jahresnachweis als PDF.                       |
| **M6 Feldtest**              | Seed mit realistischen Daten, Playwright-E2E, Probelauf mit dem Verein                                                                                                                   | Ein echter Schießtag läuft ohne Papierliste.                           |

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

- **Schießbuch-Nachweis:** Welche Pflichtangaben und welches Format verlangt der Verband? Dafür brauchen wir ein Muster vom Verein.
- **Zeitfenster:** Feste Slots (z. B. 30 oder 60 Minuten) oder freie Zeiten?
- **Gäste:** Braucht ein Gast Name und Adresse, oder reicht der Name?
- **Bahnen:** Werden sie einzeln vergeben oder nur gezählt?
- **Visuelle Richtung:** noch offen. Klären wir mit `/impeccable shape` für S3 und S6, bevor M3 beginnt.
- **Repo-Name und Hosting:** GitHub-Organisation, Domain für den Verein.
