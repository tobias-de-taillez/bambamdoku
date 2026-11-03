# Bau-Notizen zum Umbau auf das Session-Modell

Stand: 03.11.2026. Der Plan aus [UMBAU-SESSION.md](UMBAU-SESSION.md) wurde am Stück gebaut, ohne Rückfragen. Hier steht alles, was beim Bau selbst entschieden, abweichend umgesetzt oder bewusst offen gelassen wurde. Diese Punkte gehören ins gemeinsame Review.

## Ablauf

| PR  | Inhalt                                                                                                                                | Stand            |
| --- | ------------------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| #54 | Grundlage: `users.adult_confirmed_at`, `users.kiosk_pin_hash`                                                                         | gemergt          |
| #55 | PR 10: Disziplinkatalog in den Einstellungen wählbar                                                                                  | gemergt          |
| #56 | Teil (d): Mitgliedschaftsbescheinigung mit Verband, Vereinsnummer, Ausstellungstag                                                    | gemergt          |
| #57 | K1–K3: Gruppen, Serien-Verknüpfung, CSV-Import, Erinnerung, Kalender-Abo, „Aufsicht gesucht“, Passkeys, Starter-Kit-Reste stillgelegt | gemergt          |
| #58 | PR 1–2: Mitgliedsantrag, Aufnahme, Selbstregistrierung                                                                                | gemergt          |
| #59 | PR 3–4b: Session-Datenmodell, Domäne, Standansicht                                                                                    | gemergt          |
| #60 | Anleitung und Ankunftsseite auf den Session-Ablauf umgestellt                                                                         | gemergt          |
| #61 | Altfehler: „Angemeldet bleiben“ wirkte nie (Checkbox)                                                                                 | gemergt          |
| #62 | Barrierefreiheit der offenen Session; Kontrast der grünen Karte                                                                       | gemergt          |
| #63 | PR 7–9: Korrektur durch den Admin, Drucke mit Schüssen, Teilnahmenachweis, Session-Protokoll                                          | gemergt          |
| #64 | PR 5–6: „Heute schießen“ am eigenen Gerät, Kiosk mit PIN je Mitglied                                                                  | gemergt          |
| #65 | Doku auf den gebauten Stand, diese Notizen                                                                                            | gemergt          |
| #66 | Testkonten: Demo-Mitglieder mit Volljährigkeitsvermerk und Kiosk-PIN `1234`                                                           | gemergt          |
| #67 | Öffentliche Startseite unter `/`                                                                                                      | gemergt          |
| #68 | Durchsicht: Vier-Augen-Regel über die Korrekturkette, Storno-Trigger, Drosseln, Kiosk-Sperre                                          | gemergt          |
| #69 | Durchsicht: „Heute schießen“, nicht abgezeichnete Einträge, Ankunft, Protokoll, Datenschutz                                           | gemergt          |
| #70 | Bau-Notizen: Testkonten, Startseite, Durchsicht, offene Punkte                                                                        | gemergt          |
| #71 | MariaDB: Migrationen laufen (DDL ohne Transaktionsklammer, Index für den Fremdschlüssel)                                              | gemergt          |
| #72 | Review: E-Mail-Bestätigung für Anträge bei echtem Mailversand, Abmelden führt zur Anmeldung                                           | gemergt          |
| #73 | Demo-Daten mit abgezeichneter Session (letzter Befund der Durchsicht)                                                                 | gemergt          |
| #74 | Hotfix: PDFs in Produktion („Cannot resolve public path“)                                                                             | gemergt, deployt |
| #75 | Aufsichtsregister: Hinweis auf dem eigenen Profil statt stummem Fehler                                                                | gemergt, deployt |
| #76 | Aufbewahrung (Q6): Löschlauf für Kladde und Gastnamen                                                                                 | gemergt, deployt |
| #77 | Report Bug / Request Feature: schwebender Knopf mit Bildschirmfoto und Seitenzustand                                                  | gemergt, deployt |

## Eigene Entscheidungen

### Vorgehen

- **Merge ohne Warten auf die GitHub-CI.** Auto-Merge ist im Repo abgeschaltet. Vor jedem Merge liefen lokal: `npm run build`, `npm run check`, `npm run types:check`, PHPStan, Pint und die PHP-Tests, bei Änderungen am Ablauf auch Playwright. Die CI läuft danach auf `main`. Deployt wird erst, wenn sie grün ist (`scripts/deploy-hetzner.sh` prüft das).
- **Kein Deploy in Produktion.** Der Plan verlangt die Umstellung außerhalb eines Schießtags und vorher eine Sicherung. Deployen ist ein Schritt nach außen, den der Vorstand bewusst anstößt.
- **Gemeinsame Spalten vorab (#54).** So bauen die parallelen Stränge auf derselben Migration auf und erzeugen keine zwei konkurrierenden.

### PR 10 Disziplinkatalog

- `config/disziplinen.php` bleibt leer. Die SpO-Nummern muss der Verband liefern (O1), geraten werden sie nicht. Bis dahin wird keine Disziplin abgefragt und es gibt keinen Teilnahmenachweis je Disziplin.
- Die Auswahl „Keiner“ in den Einstellungen setzt sich nicht gegen einen Katalog durch, der in der `.env` gesetzt ist. Leere Einstellungswerte fallen auf die Konfiguration zurück, wie bei allen anderen Einstellungen.

### Mitgliedschaftsbescheinigung (#56)

- Der Text lautet „seit … Mitglied und am Ausstellungstag Mitglied“. Fehlt „Mitglied seit“, entfällt der Satzteil, statt eine Leerzeile zu drucken.
- Ein Hinweis stellt klar, dass die zehn Jahre die Behörde prüft. Der Verein hat keine WBK-Daten.
- Die Sperre für Anträge kommt mit dem Mitgliedsantrag dazu.

### K1–K3 Komfortfunktionen (#57)

- Das Model `Group` ist ganz gelöscht. Die Tabellen bleiben, wiederherstellbar ist es aus Git.
- Der Demo-Tag „Jugendtraining“ bleibt, ist aber jetzt für alle sichtbar.
- Neuer Helfer `app/Support/MailDelivery`. Ohne echten Mailer meldet die Absage: „Der Mailversand ist nicht eingerichtet, niemand wurde benachrichtigt.“
- Der Schalter heißt „Gastschützen erlaubt (nur für die Online-Buchung)“.
- Eine Datenmigration leert `calendar_token`, damit alte Kalender-Links tot bleiben.
- `@laravel/passkeys` ist aus `package.json` und dem Lockfile entfernt, das Lockfile von Hand bereinigt (offline). Beim nächsten `npm ci` ist auch `node_modules` sauber.
- **Vor dem Deploy prüfen:**
    - Auf einer Sicherung `SELECT COUNT(*) FROM shooting_days WHERE restricted_to_groups = 1` ausführen. Solche Tage sind jetzt für alle sichtbar.
    - Bestehende Kalender-Abos liefern 404. Die Mitglieder vorher informieren.
    - K3 (Passkeys raus) erst deployen, wenn SMTP und „Passwort vergessen“ funktionieren. Sonst kommt nicht mehr ins Konto, wer sich nur per Passkey anmeldete.

### PR 1–2 Mitgliedsantrag und Selbstregistrierung (#58)

- Die Antragslogik liegt in `app/Domain/Applications.php`. Ablehnen löscht den Antrag samt Sitzungen; der Audit-Eintrag nennt nur die ID, keine Personendaten.
- Fehlerantworten:
    - Bearbeiten-Seite: Weiterleitung auf „Anträge“.
    - Speichern, Einladung, Register: 403.
    - Auszug, Bescheinigung, Aufnehmen und Ablehnen bei Nicht-Anträgen: 404.
- Die Mitgliedsnummer ist beim Aufnehmen optional, wie beim Anlegen.
- Anträge bekommen keine Bestätigungsmail, sie können sich ohnehin nicht anmelden.
- Der Throttle der Registrierung erlaubt 10 Versuche je IP und Stunde.
- Der Link auf der Anmeldung heißt „Mitgliedschaft beantragen“.
- Der Löschlauf `verein:antraege-loeschen` läuft täglich um 04:15. Er entfernt Anträge, die älter als 30 Tage sind.
- **Risiken:**
    - Die E-Mail-Adresse aus der Registrierung wird nie per Mail bestätigt; die Aufnahme setzt `email_verified_at`. Der Vorstand sollte die Adresse bei der Ausweisprüfung mit abgleichen.
    - „E-Mail schon vergeben“ verrät, ob eine Adresse registriert ist.
    - Der Throttle hängt an der IP. Hinter einem Proxy ohne TrustProxies teilen sich alle dasselbe Limit.
    - Altlast, nicht im Browser geprüft: „Angemeldet bleiben“ schickt wegen der Checkbox-Komponente vermutlich nie „an“.

### PR 3–4b Session-Kern (#59)

- **Probe-Migration:** an einer Kopie der Produktionssicherung vom 03.11.2026, 09:24, mit SQLite 3.51.
    - Die vier neuen Migrationen laufen durch, ein zweiter Lauf ändert nichts.
    - `integrity_check` ok, alle 13 Trigger vorhanden.
    - Bestand in Produktion: 0 Kladde-Einträge, 0 Gruppentage, 0 Serien, 0 Kalender-Tokens, 3 Konten. Die Deploy-Hinweise aus K1/K2 sind damit gegenstandslos.
    - Die Kopie ist danach gelöscht.
- Ablehnen mit Schnellgründen kam schon mit 4b, nicht erst mit PR 5.
- Ein zweites „Session starten“ liefert die offene Session zurück statt eines Fehlers.
- Grenzen: Schüsse 1 bis 999, Ablehnungsgrund mindestens 3 Zeichen.
- Die Route `enter` bleibt als Ein-Schritt-Weg für die Aufsicht: Sie aktiviert und erfasst in einem Schritt.
- Aktivieren aus einer Buchung verlangt eine Schicht auf diesem Stand. Über die Suche geht es ohne Schicht (E4).
- Zusätzliche Trigger-Regeln:
    - „abgelehnt“ verlangt Grund und Person;
    - neue Einträge kommen nur als „offen“ hinein;
    - „abgezeichnet“ verlangt die Uhrzeit;
    - Kiosk-Tokens sind eindeutig.
- Die Uhrzeit „bis HH:MM“ im Abzeichnen-Dialog kommt vom Gerät, die im Protokoll vom Server.
- **Risiko:** Der MySQL-Zweig der Migrationen ist ungetestet, Produktion läuft auf SQLite. Unter InnoDB könnte das Löschen des alten Unique-Index am Fremdschlüssel scheitern.

### Texte und Altfehler (#60, #61)

- Die Anleitung in der App und die QR-Ankunftsseite beschreiben jetzt den Session-Ablauf.
- Die Checkbox-Komponente schickte ihr verstecktes Feld immer mit. Deshalb wirkte „Angemeldet bleiben“ beim Login nie. Jetzt verhält sie sich wie eine native Checkbox.
- **#62:** Der axe-Check prüft jetzt auch die offene Session und den Abzeichnen-Dialog. Die grüne Bestätigungskarte (Weiß auf `emerald-600`) unterschritt WCAG AA und ist jetzt dunkler. Die Toasts aus svelte-sonner sind vom Check ausgenommen, weil ihre Listenstruktur aus der Bibliothek kommt.

### PR 7–9 Korrektur und Drucke (#63)

- **Korrektur:**
    - Schüsse sind beim Korrigieren optional (1 bis 999), damit Altbestand ohne Schusszahl korrigierbar bleibt.
    - Der Grund braucht mindestens 5 Zeichen. Die alte Zeile bekommt als Storno-Grund „Korrektur: …“.
    - Gibt es mit den neuen Angaben am Tag schon eine gültige Zeile, wird die Korrektur abgewiesen und das Storno zurückgerollt.
    - Die Korrekturzeile übernimmt `session_entry_id` nicht, sonst kollidiert der Unique-Index. Die Herkunft läuft über `corrects_id`.
- **Sperre für eigene Zeilen:** Sie kommt als Validierungsfehler. An eigenen Zeilen sind die Knöpfe ausgeblendet.
- **Passwort:** Es wird vorab bestätigt, über die Route `admin.record.confirm` und den Hinweis „Passwort bestätigen“. So geht keine Eingabe verloren. Die Knöpfe für Nachtrag, Storno und Korrektur erscheinen erst danach. Nach der Bestätigung lädt die Kladde einmal komplett neu.
- **Sessions in der Admin-Kladde:** Statt einer eigenen Liste der verfallenen Sessions zeigt sie alle Sessions im Filterzeitraum und markiert die verfallenen. Dort sitzt auch der Knopf „Protokoll“.
- **„Aktiviert“** steht nur in der Oberfläche (Buchungsliste). Enum und Protokoll sagen intern weiter `checked_in` bzw. „Anwesend“.
- **Teilnahmenachweis:**
    - Die Disziplin geht als Query-Parameter mit, weil ihre Schlüssel einen Doppelpunkt enthalten.
    - Ohne Katalog oder bei unbekannter Disziplin kommt 404.
    - Knöpfe gibt es nur für Disziplinen mit Einträgen, im Admin-Formular über die letzten 24 Monate.
    - Ein Test prüft, dass keines der Wörter „erfüllt“, „Bedürfnis“, „glaubhaft“, „regelmäßig“, „erfolgreich“, „absolviert“ vorkommt.
- **Session-Protokoll:**
    - Nur für den Admin; auch die Aufsicht der Session bekommt 403 (E14).
    - Bei einer stornierten Zeile mit Korrektur steht nur die Korrektur da.
    - Einträge, die beim Abzeichnen auf eine schon vorhandene gleiche Zeile trafen, sind als „ohne eigene Kladde-Zeile“ markiert.

### PR 5–6 eigenes Gerät und Kiosk (#64)

- **Teilnehmer:** Der Server bestimmt ihn immer aus der jüngsten Aktivierung von heute, nie aus dem Request.
- **Kiosk-PIN:**
    - Sie wird zweimal geprüft: vorab, damit das Formular erst nach richtiger PIN erscheint, und beim Eintragen noch einmal. Die Sperre nach 5 Fehlversuchen für 15 Minuten zählt beide Wege. Während der Sperre wird auch die richtige PIN abgewiesen.
    - Vor der richtigen PIN zeigt der Kiosk nur Name, Mitgliedsnummer und ob eine PIN gesetzt ist. Die Vorbelegung aus früheren Einträgen kommt erst danach.
    - PIN setzen und löschen verlangt die Passwortbestätigung auch beim Speichern. Das Mitglied selbst und der Vorstand erzeugen dabei einen Audit-Eintrag.
    - `pin` wird nie in die Sitzung geflasht.
- **Kiosk-Link:** Unbekannt oder ersetzt ergibt 404. Ist die Session abgezeichnet oder verfallen, kommt die Seite „Session beendet“.
- **Kurz/Lang** hängt am Umfang der Aufsicht der Session, nicht am Mitglied.
- **Eigene Formular-Komponente** `SelfEntryForm` statt eines Umbaus von `EntryForm`, damit die Stand-Abläufe stabil bleiben.
- **Hinweis bei einem Eintrag über Aufsicht oder Kiosk:** „Ändern nur über die Aufsicht“, weil das Mitglied ihn nicht selbst zurückziehen kann.
- **Abgelaufene Seite (419):** Eine allgemeine Regel lädt neu statt hängenzubleiben. Geprüft ist sie nur im Feature-Test.
- **Playwright:** Die neue Spec `session-device.spec.ts` legt sich per `artisan tinker` eigene Testpersonen an.
- **Nicht im Browser getestet:** „Kiosk auf diesem Gerät“. Der Feature-Test zeigt, dass nur dieses Gerät abgemeldet wird.

### Testkonten (#66)

- Testkonten gibt es nur in den Demo-Daten (`DemoSeeder`), nicht in Produktion. Die Seite ist öffentlich erreichbar, ein Konto mit bekanntem Passwort wäre dort eine offene Tür. Produktion hat drei echte Konten (Tobias, Magnus, Test-User) und keine Registerzeile.
- Alle volljährigen Demo-Mitglieder haben den Volljährigkeitsvermerk und die Kiosk-PIN `1234`. Tim Jungschütze hat beides nicht und zeigt so die Abfrage „volljährig geprüft“. Die Liste der Konten steht in der README.

### Startseite (#67)

- `/` zeigt Gästen eine schlichte Startseite mit:
    - Vereinsname und Anschrift aus den Einstellungen;
    - „Anmelden“, dazu „Mitgliedschaft beantragen“ nur bei offener Registrierung;
    - einem Bereich „So sieht es aus“ mit zwei Bildern: dem Kiosk am Stand, wie ihn Mitglieder sehen, und der Planung der Schießtage durch den Vorstand (seit der Überarbeitung; vorher standen hier vier nummerierte Schritte);
    - einer Fußzeile mit Datenschutz, Impressum (nur wenn gepflegt) und „Open Source“, ohne Link aufs Repository.
- Sie zeigt keine echten Termine, Namen oder Zahlen. Die Bilder stammen aus den Demo-Daten und liegen als WebP in hell und dunkel unter `public/images/startseite/`.
- Neu aufnehmen nach Änderungen an Kiosk oder Schießtage-Liste: `npm run screenshots:startseite` (braucht `cwebp`, z. B. über `brew install webp`). Das Skript läuft nicht in der CI.
- Angemeldete Nutzer werden aufs Dashboard weitergeleitet. Der Routenname `home` bleibt.
- Nach dem Abmelden landet man jetzt auf der Startseite statt auf der Anmeldung. Am gemeinsam genutzten Gerät am Stand ist das ein Klick mehr.

### Q6 Aufbewahrung und Löschlauf

Branch `feat/aufbewahrung`, umgesetzt nach Empfehlung A (Kladde 5 Jahre, Gastnamen 12 Monate, beides in den Einstellungen).

- **Ausnahme statt Lockerung:** Die Trigger aus …_001100, …_001300 und …_001500 sind wörtlich übernommen und nur um eine Ausnahme ergänzt (…_001600).
    - Sie greift nur, solange in `retention_runs` ein Lauf offen ist (`finished_at IS NULL`), und nur für Termine vor dessen Stichtag.
    - Ein Stichtag jünger als 3 Jahre (Kladde) bzw. 1 Monat (Gastnamen) öffnet nichts. Diese Untergrenze rechnet die Datenbank mit ihrer eigenen Uhr, auch ein gefälschter Lauf kommt nicht darunter.
- **Anonymisieren nur in genau einer Form:** `Gast (anonymisiert #<Zeilennummer>)`, nur an Gastzeilen (`user_id IS NULL`). Alle anderen Spalten bleiben unverändert, auch `updated_at` und die Storno-Felder. Die Nummer hält die Unique-Indizes auf `guest_name` eindeutig. Stornierte Gastzeilen werden mit anonymisiert.
- **Ein Lauf, eine Transaktion (IMMEDIATE):** Zeile in `retention_runs` anlegen, löschen, anonymisieren, Zahlen und `finished_at` setzen, Protokolleintrag `retention.run` ohne Personendaten.
    - Andere Verbindungen sehen nie einen offenen Lauf.
    - Steht trotzdem einer in der Tabelle, kann er nur von einem Eingriff stammen. Dann bricht der Lauf mit Meldung ab.
    - `retention_runs` ist selbst geschützt: keine Löschung, ein Lauf wird genau einmal abgeschlossen, Stichtage und Start sind fest.
- **Probelauf und leerer Lauf:** Beide rollen die Transaktion zurück. Der Probelauf (`--dry-run`) zählt damit genau, was ein Lauf täte. Ein Lauf ohne Arbeit hinterlässt weder eine Zeile noch einen Protokolleintrag.
- **Stichtag:** heute minus Jahre bzw. Monate, ohne Monatsüberlauf. Termine davor fallen unter die Frist, der Stichtag selbst nicht.
    - Die Datenbank rechnet in UTC. Kurz nach Mitternacht oder bei der Mindestfrist wäre der Stichtag sonst einen Tag jünger als die Untergrenze, und der Lauf bräche am Trigger ab. Dann nimmt er die Untergrenze.
    - Werte außerhalb der Grenzen (Handeingriff in `settings`) werden auf 3–10 Jahre bzw. 1–60 Monate begrenzt. Die Einstellungen weisen sie ab, ebenso Gastnamen, die länger bleiben sollen als die Kladde.
- **Was gelöscht wird:**
    1. Kladde-Zeilen, in einem Statement (wegen `corrects_id`).
    2. Vorstufe, Teilnehmer und Sessions, in dieser Reihenfolge (RESTRICT).
    3. **Buchungen** der Tage vor dem Stichtag. Sie stehen nicht ausdrücklich im Auftrag. Mit Check-in sind sie aber eine zweite Anwesenheitsliste und sollen die Kladde nicht überdauern. `bookings_keep_logged` bleibt unverändert.
    - Was noch von einer verbliebenen Kladde-Zeile referenziert wird, bleibt stehen. Im Ablauf kommt das nicht vor, weil eine Korrektur das Datum der Ursprungszeile trägt.
    - Schießtage, Aufsichten, Aufsichtsregister, Mitglieder und das übrige Protokoll bleiben.
- **Protokoll:** Die Namen der betroffenen Gäste werden in Protokolltexten vor dem Stichtag zu „Gast (anonymisiert)“.
    - Als ganze Wörter, längste zuerst („Max“ ändert „Maximiliane“ nicht), dazu das Muster „Gast … gebucht:“, auch bei schon gelöschten Buchungen.
    - Nur Texte zu Buchungen, die keinem Mitglied gehören. Gastnamen stehen nur dort (`Bookings`); ein Text über ein Mitglied gleichen Namens bleibt so unberührt.
    - Die Namen werden vor dem Löschen gesammelt, damit auch gelöschte Gastzeilen erfasst sind.
- **MySQL/MariaDB:** Die Migration legt nur die Tabelle an. Der Löschlauf verweigert sich mit Meldung, die Trigger dort bleiben ohne Ausnahme.
- **Statistik:** Anonymisierte Gäste zählen je Zeile als eigene Person. Die Personentage bleiben gleich.

## Unabhängige Durchsicht nach dem Bau (#68, #69)

Vier Prüfer (Recht und Daten, Sicherheit, Plan-Vollständigkeit, Abläufe und Texte) haben den gemergten Stand durchgesehen. Je Blickwinkel hat ein zweiter Prüfer jeden Befund zu widerlegen versucht. Bestätigt wurden 15 Befunde, davon 4 schwere; widerlegt wurde keiner. Behoben sind alle 15 (der letzte in #73).

- **Schwer, behoben:**
    - Die Vier-Augen-Regel (E3) ließ sich über eine Korrekturkette umgehen. Jetzt wird die ganze Kette über `corrects_id` geprüft.
    - Auf „Heute schießen“ fehlten nach der Aktivierung die Kategorien, und es wurde still „erlaubnisfrei“ gespeichert.
    - Mitglieder sahen verfallene, abgelehnte und zurückgezogene Einträge nach dem Session-Tag nicht mehr. Jetzt stehen sie in der Kladde unter „Nicht in der Kladde“.
- **Leicht, behoben:**
    - Der Storno-Trigger verlangt Grund, Admin und Zeitpunkt (Migration 001500).
    - Ein Nachtrag ohne Katalog speichert keinen Freitext als Disziplin mehr.
    - Die Passwortbestätigung ist gedrosselt.
    - Die PIN-Sperre zählt vor der Prüfung.
    - Der Kiosk fällt nach 90 s ohne Eingabe zurück auf die Liste.
    - Die Ankunftsseite kennt „abgezeichnet“.
    - Doppelt erfasst ergibt keine grüne Karte mehr.
    - Das Protokoll hat Bezeichnungen für alle neuen Aktionen.
    - Der Datenschutzhinweis ist ergänzt.
- **Eigene Entscheidungen dabei:**
    - Die Drossel der Passwortbestätigung zählt jeden Versuch, auch erfolgreiche: 5 je Minute.
    - Bei einer neuen Buchung nach dem Abzeichnen meldet die Ankunftsseite wieder „gemeldet“, damit die nächste Aufsicht „ist da“ sieht.

## Noch offen

- **DemoSeeder:** behoben in #73. Gestern gibt es eine abgezeichnete Session: Max am Handy, Erika durch die Aufsicht, ein abgelehnter Eintrag. Damit sind alle 15 Befunde der Durchsicht behoben.
- **Standansicht nach dem Abzeichnen:** Teilnehmer, die über die Suche aktiviert wurden, behalten in der Buchungsliste ihr Abzeichen. Auch die Buchung wird beim Abzeichnen nicht angepasst. Das ist nur kosmetisch, der Nachweis hängt allein an der Kladde.
- **MariaDB** (lokal mit MariaDB 13.0.2 geprüft, #71):
    - Zwei echte Fehler sind behoben. MariaDB beendet Transaktionen bei DDL-Befehlen selbst, deshalb läuft die Transaktionsklammer nur noch auf SQLite. Außerdem bekommt der Fremdschlüssel auf `user_id` einen eigenen Index, bevor der alte Unique-Index fällt.
    - Alle Migrationen laufen, auch wiederholt, und die Demo-Daten lassen sich einspielen.
    - Von der Testsuite laufen 336 von 348 Tests. 7 Fehlschläge sind SQLite-spezifisch: die Sicherung per `VACUUM INTO` und Abfragen auf `sqlite_master`.
    - Offen sind 3 echte Lücken. Der Doppelschutz greift ohne Disziplin nicht, weil der Index kein `coalesce` hat. Zwei Trigger-Abweisungen greifen im Test nicht: Eine abgezeichnete Session lässt sich wieder öffnen, und offene Einträge lassen sich ändern. Die Ursache ist nicht geklärt.
    - MariaDB ist deshalb für den Echtbetrieb noch nicht freigegeben. Produktion läuft auf SQLite.
    - Nachprüfung am 03.11.2026, 15:40, auf einer frischen MariaDB: Die zwei Trigger-Abweisungen greifen dort doch, ihr Fehlschlag kam von einem halb migrierten Stand. Echt offen bleiben:
        - der Doppelschutz ohne Disziplin; eine Lösung wäre eine gespeicherte generierte Spalte `discipline_key` mit `COALESCE(discipline, '')` im Unique-Index;
        - die Hilfe-Meldungen (#77), die auf MariaDB mit 500 abbrechen;
        - der Löschlauf (#76), der dort bewusst verweigert.

        Von der Testsuite laufen 348 von 376 Tests. MariaDB voll zu unterstützen ist ein eigenes Vorhaben; der Plan klammert es aus (Q8 = A).
- **Barrierefreiheit:** Die neuen Anzeigen „Nicht in der Kladde“, der Duplikat-Hinweis und der Zustand „abgezeichnet“ sind nicht mit axe geprüft, weil die Testdaten sie nicht auslösen.
- **Vor dem Deploy:**
    1. SMTP-Zugangsdaten setzen.
    2. Eine Registerzeile für eine Aufsicht eintragen.
    3. Den Deploy außerhalb eines Schießtags machen; das Skript sichert vorher automatisch.
- **Von außen:** Disziplinkatalog des Verbands (O1) und die Fragen an den Verband in UMBAU-SESSION.md.
- **Q6, Aufbewahrungsfristen:** Der Löschlauf ist gebaut (Branch `feat/aufbewahrung`, siehe oben). Offen bleiben:
    - Der Verein muss die Fristen beschließen und ins Verzeichnis nach Art. 30 DSGVO eintragen.
    - Datensicherungen enthalten gelöschte Daten bis zu 30 Tage, heruntergeladene Sicherungen länger. Der Datenschutzhinweis sagt das.
    - Freitexte (`late_reason`, `cancel_reason`, `reject_reason`) können Gastnamen enthalten. Sie werden nicht anonymisiert, die Trigger schützen sie weiter.
    - Eine künftige Migration, die einen dieser Trigger neu anlegt, muss die Ausnahme mitnehmen. Sonst bricht der Lauf ab, gelöscht wird dann nichts.
    - Unter MariaDB nennt der Datenschutzhinweis Fristen, die dort nicht durchgesetzt werden.
    - **Protokoll (`audit_entries`) wird nie gelöscht.** Texte wie „Aktiviert: Name, Datum“ (`booking.checked_in`), `booking.arrived` und `user.adult_confirmed` behalten Name und Datum eines Mitglieds über die Frist der Kladde hinaus. Das ist dasselbe Argument wie bei den Buchungen. Ob und wann das Protokoll gekürzt wird, muss der Verein entscheiden.
    - Freitext im Protokoll (z. B. Grund einer Absage, `shooting_day.cancelled`) wird nicht durchsucht.
- **Playwright:** Einmal schlug ein Lauf vereinzelt fehl. Der direkte Wiederholungslauf war 33 von 33 grün.

## Review mit dem Nutzer (03.11.2026, 13:45)

- **E-Mail im Antrag:** Geändert (#72).
    - Sobald echter Mailversand läuft, bekommt ein Antrag einen signierten Bestätigungslink, 7 Tage gültig.
    - Aufnehmen geht erst nach der Bestätigung. Versucht der Vorstand es vorher, geht der Link erneut raus.
    - Ohne SMTP bleibt es wie vorher: Der Vorstand gleicht die Adresse bei der Ausweisprüfung ab.
- **Abmelden:** Geändert (#72), es führt wieder direkt zur Anmeldung. Die Startseite erreicht man über den Link dort.
- **Aktivieren aus der Buchungsliste nur mit Schicht auf diesem Stand:** bestätigt, bleibt.
- **Kiosk-Sicherheit** (PIN 4–6 Ziffern, Sperre nach 5 Fehlversuchen für 15 Minuten, 90 Sekunden Inaktivität): bestätigt, bleibt.

## Nach dem Bau (03.11.2026, nachmittags)

- **Deploys:** um 14:28 (#73), 14:50 (#75 mit #74) und 15:20 (#77 mit #76). Jeder Deploy sicherte vorher die Produktionsdatenbank nach `~/bambamdoku-backups/`.
- **SMTP:** über das Postfach `bambamdoku@irazari-te.ch` auf `mail.your-server.de:587`. Das Postfach-Passwort steht im macOS-Schlüsselbund (`hetzner-irazari-mail`). Ein Skript hat es direkt in die Server-`.env` geschrieben, ohne es anzuzeigen. Die alte `.env` liegt als `.env.vor-mail-20261103-1435` auf dem Server; sie enthält schon die Mail-Einstellungen, weil ein zweiter Lauf in derselben Minute sie überschrieb.
- **PDF-Hotfix (#74):** Auf dem Webhosting liegt der Docroot außerhalb der App. laravel-dompdf suchte `base_path('public')` und brach jedes PDF ab. Jetzt zeigt `dompdf.public_path` auf `public_path()`. Lokal fiel das nicht auf, weil beide Pfade dort gleich sind.
- **Register (#75):** Die eigene Registrierung als Aufsicht trägt ein anderes Vorstandsmitglied ein (Vier-Augen-Prinzip). Das Formular war auf dem eigenen Profil trotzdem sichtbar und scheiterte stumm.
- **Löschlauf (#76):** Q6 hat der Nutzer nach 14 Uhr freigegeben. Selbst entschieden:
    - Buchungen vor dem Stichtag werden mit gelöscht, eine Buchung mit noch hängender Kladde-Zeile bleibt.
    - Der Probelauf rollt zurück.
    - Freitexte (Nachtrags-, Storno-, Ablehnungsgrund) werden nicht anonymisiert.
    - Das Protokoll wird nie gekürzt, darüber muss der Verein entscheiden.
- **Report Bug / Request Feature (#77):** Selbst entschieden:
    - Den Knopf gibt es auch auf Fehlerseiten; dafür hat die App jetzt eine eigene Fehlerseite.
    - Routennamen `help-requests.*`, weil `help` schon die Anleitung ist.
    - Die Kennung hat 6 Zeichen.
    - Erledigte Meldungen werden nach 90 Tagen gelöscht, alle anderen nach 365 Tagen.
    - Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO.
    - Ohne Bild geht die Meldung trotzdem raus, wenn das Bildschirmfoto scheitert oder länger als 8 s dauert.
- **Abholen der Meldungen:** `scripts/hilfe-holen.sh` spiegelt sie nach `~/bambamdoku-hilfe/`.

## Rollen-Rundgang (03.11.2026, abends)

Browser-Tests, die alle Rollen und ihr Zusammenspiel durchspielen und jeden Schritt mit einem Foto belegen. Sie liegen unter `tests/rollen/`, gestartet wird mit `npm run test:rollen`. Der Bericht entsteht unter `test-results-rollen/rollen/bericht/index.html`.

**Aufbau:**

- `matrix.ts` ist der Vertrag: 48 Zeilen der Form „A tut X → B sieht Y“, dazu die Grenzen der Rollen.
- 9 Geschichten in 7 Dateien rufen `beweis(page, text, { zeige, belegt })` auf.
- Ein Foto entsteht erst, wenn `zeige` sichtbar ist. Dazu kommen die `expect` davor.
- Ein roter Rahmen markiert das Geprüfte.
- Jede Person fotografiert auf ihrem Gerät: Vorstand am Rechner, Aufsicht und Kiosk am Tablet, Mitglieder am Handy.
- Die Rolle, die die Wirkung sieht, macht das entscheidende Foto.

**Selbst entschieden:**

- **Eigener CI-Workflow `rollen`.** Die Tests stehen nicht in `tests`. `scripts/deploy-hetzner.sh` wartet nur auf `tests`, eine wacklige Geschichte blockiert so keinen Deploy. Der Bericht kommt als Artefakt `beweisbericht` mit, 30 Tage aufbewahrt.
- **Schranke:**
    - Bleibt bei einem vollen Lauf eine Matrix-Zeile ohne Foto aus einer bestandenen Geschichte, ist der Lauf rot. Bei Datei- oder `--grep`-Filtern gilt die Schranke nicht.
    - **Bekannte Fehler** stehen in der Matrix mit `offen`; die Geschichte zeigt sie mit `befund` statt `belegt`. Der Bericht färbt sie orange.
    - Ist ein Fehler behoben, meldet der Lauf: „offen“ entfernen.
- **Jede Geschichte startet mit dem frischen Demo-Verein.** Die Vorlage wird je Lauf einmal geseedet und vor jeder Geschichte kopiert.
- **Parallel lauffähig:** Mit `ROLLEN_LAUF` und `ROLLEN_PORT` bekommt jeder Lauf eigene Datenbank, eigenen Port und eigenen Bericht.
- **Wochentag:** An Mittwochen und Samstagen hat der Demo-Verein einen Termin am selben Tag. `ROLLEN_TERMIN_HEUTE=1` stellt das an jedem Wochentag nach; beide Varianten sind grün.
- **Nachtpause:** Zwischen 23:30 und Mitternacht überspringt der Rundgang alle Geschichten. Termine von heute wären vorbei, offene Einträge verfallen um Mitternacht. Die Schranke meldet das dann, statt rot zu werden.
- **Matrix nach dem echten Verhalten formuliert,** wo die App bewusst anders arbeitet als zuerst angenommen:
    - Abgelehnte Anträge werden gelöscht.
    - Korrekturen stehen in der Kladde des Vereins, nicht im Protokoll unter Verwaltung.
    - Nach dem Wiederöffnen eines abgesagten Tages bleiben die Buchungen storniert.
    - Ohne Disziplinkatalog gibt es keinen Teilnahmenachweis.

**Gefundene Fehler in der App:** nicht behoben, zur Entscheidung.

- Als `offen` in der Matrix:
    - **K8:** Nach einem Storno zeigt „Heute schießen“ den Eintrag weiter als „abgezeichnet“. In der eigenen Kladde verschwindet er dagegen kommentarlos.
    - **V6:** Wird einer Aufsicht das Register entzogen, nimmt ihre laufende Session am Kiosk und am Handy weiter Einträge an. Abzeichnen kann sie niemand, um Mitternacht verfallen sie.
    - **G9:** Eine Aufsicht, die nur für erlaubnisfreie Waffen registriert ist, kann bei der Planung die Aufsicht für 25 m Kurzwaffe übernehmen. Mitglieder können danach Kurzwaffe buchen.
- Weitere:
    - `/impressum` zeigt Gästen die Seitenleiste für Mitglieder; im Layout-Resolver fehlt `Imprint`.
    - „Einladungslink … wurde per E-Mail verschickt“ erscheint auch ohne eingerichteten Mailversand.
    - Am Kiosk ist das Feld Schüsse mit Werten aus abgelehnten Einträgen vorbelegt (999).
    - Ein deaktiviertes Mitglied bekommt bei neuer Anmeldung nur „Zugangsdaten stimmen nicht“, keinen Hinweis.
    - Am Handy liegt die Spalte Schüsse der eigenen Kladde außerhalb des Bildschirms.
    - Kleinere Texte:
        - „1 Buchungen“;
        - die Rolle „(member)“ in Rückmeldungen;
        - „Minderjährige weiter auf Papier.“ doppelt im Gast-Formular;
        - „Haken nötig“ bleibt stehen, obwohl der Haken gesetzt ist.
- Design-Lücke ohne Zeile: Die App kennt kein Alter. Den Haken „volljährig geprüft“ kann die Aufsicht auch bei einem Minderjährigen setzen.

## Rückmeldungen aus der App umgesetzt (03.11.2026, spät)

Zwei Vorschläge kamen über „Report Bug / Request Feature“, beide von einem Vorstandsmitglied: 5M3GFW zu Terminen, P2A56K zu Mitgliedern. Der Nutzer hat entschieden: „bau die ein und push das live“. Damit ist K2 für den CSV-Import aufgehoben, siehe ENTSCHEIDUNGEN.md.

- **Schießtage wiederholen:**
    - Neu sind „alle zwei Wochen“, „monatlich am selben Wochentag“ und „monatlich am selben Tag“.
    - Beim Wochentag sind mehrere Wochen wählbar, z. B. jeden 1. und 3. Samstag, auch „letzter“.
    - Das Formular zeigt eine Vorschau der Termine. Server und Browser rechnen gleich; geprüft sind 2000 Zufallsfälle.
    - Einzelne Termine bleiben unverknüpft, wie bei der wöchentlichen Mehrfachanlage (keine Serien-Verknüpfung, K1).
- **Schießtage importieren:**
    - Erst kommt die Vorschau, dann die Bestätigung.
    - Jede Zeile wird geprüft wie im Formular, auch auf Überschneidungen untereinander und mit bestehenden Tagen.
    - Höchstens 500 Zeilen, Vorlage zum Herunterladen.
- **Mitglieder importieren:**
    - Der Import ist aus der Zeit vor K2 zurückgeholt und angepasst.
    - Standaufsicht geht direkt ins Aufsichtsregister, mit denselben Regeln wie beim einzelnen Eintrag:
        - Umfang, gültig bis und geprüft am;
        - Bestätigung für Volljährigkeit und Sachkunde, bei erlaubnispflichtig zusätzlich Waffensachkunde;
        - Vier-Augen-Prinzip.
- **Selbst entschieden nach zwei Prüfrunden:**
    - Ein Re-Import hebt keinen Entzug auf.
    - Wird nur über die Mitgliedsnummer zugeordnet, muss der Name passen. Verglichen wird ohne Groß- und Kleinschreibung, Satzzeichen, Titel und Reihenfolge, mit ä = ae und ß = ss.
    - Eine Nummer, die nur ohne führende Nullen passt, ist ein Fehler, keine Zuordnung. So landet nichts beim falschen Mitglied, etwa bei Vater und Sohn.
    - Beispielzeilen der Vorlage werden abgewiesen.
    - „Volljährig geprüft“ verlangt eine Bestätigung.
    - Jede Änderung steht je Mitglied im Protokoll.
    - Ein nicht geschlossenes Anführungszeichen weist die ganze Datei ab.
    - Höchstens 2000 Zeilen.
- **Tests:**
    - Feature-Tests für jede Regel.
    - Der Rollen-Rundgang belegt mit Fotos P7 (Monatsserie), P8 (Termin-Import) und A8 (Mitglieder-Import mit Aufsicht).
    - Der Test für Rückmeldungen in `tests/e2e/help.spec.ts` wartet jetzt, bis das Bildschirmfoto geladen ist. Vorher prüfte er ein lazy geladenes Bild zu früh.
