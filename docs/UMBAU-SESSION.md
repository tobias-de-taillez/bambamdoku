# Umbau auf das Session-Modell

Stand: 03.11.2026.

Grundlage:

- die Entscheidungen des Nutzers vom 03.11.2026 (Q1b, Q5, Komfortfunktionen);
- ein Abgleich mit dem Code auf main `6505254`;
- eine Rechtsprüfung mit Gesetzestext, abgerufen am 03.11.2026;
- eine Gegenkritik mit 2 Blockern, 11 Major- und 7 Minor-Befunden. Blocker und Major-Befunde sind laut Endfassung eingearbeitet, stichprobenartig gegengeprüft. Der Konflikt beim CSV-Import entfällt mit K2.

Dieses Dokument ist der Bauplan. Gebaut wurde er am 03.11.2026 in den PRs #54–#69, ohne Deploy. Was dabei selbst entschieden wurde, steht in [UMBAU-NOTIZEN.md](UMBAU-NOTIZEN.md). Die Kladde bleibt `log_entries` (Q7 = B). Neu ist eine Vorstufe, in die Mitglieder selbst eintragen. Erst das Abzeichnen der Aufsicht macht daraus Kladde-Einträge.

## Entschieden

| Punkt               | Entscheidung                                                                                                                                                                                                                                                                       |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Vereine             | Ein Verein je Installation, keine Mandanten.                                                                                                                                                                                                                                       |
| Konten              | Jeder legt selbst ein Konto an und beantragt damit die Mitgliedschaft; der Verein bestätigt. Der Verein kann auch selbst Konten anlegen.                                                                                                                                           |
| Aufsicht            | Zusätzliche Berechtigung eines Mitglieds, wie bisher als Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV).                                                                                                                                                                          |
| Schieß-Session      | Die Aufsicht startet sie am Stand und aktiviert die Mitglieder X, Y, Z. Die tragen selbst ein, auf dem eigenen Handy oder am Kiosk-Tablet. Am Ende zeichnet die Aufsicht ab. Danach ändert nur ein Admin, dokumentiert.                                                            |
| Eintrag             | Disziplin, Schusszahl, Kurz/Lang/erlaubnisfrei, eigen/Verein                                                                                                                                                                                                                       |
| Übersicht           | Jedes Mitglied sieht seine Schüsse je Tag und seine Buchungen.                                                                                                                                                                                                                     |
| Papier              | Ohne Papier ist der Goldstandard, drucken muss trotzdem gehen: (a) Teilnahmenachweis je Disziplin (bestätigt die Teilnahme, kein Urteil), (b) Übersicht aller Schießtage mit Schüssen, (c) Session-Protokoll je Tag, (d) Mitgliedschaftsbescheinigung nach § 14 Abs. 4 S. 3 WaffG. |
| Komfortfunktionen   | siehe [Komfortfunktionen](#komfortfunktionen)                                                                                                                                                                                                                                      |
| Detailfragen E1–E15 | siehe [Detailentscheidungen](#detailentscheidungen)                                                                                                                                                                                                                                |

## Detailentscheidungen

Entschieden am 03.11.2026: alle wie empfohlen, nur E2 nicht (Kiosk mit PIN). Der Bauplan unten folgt diesen Entscheidungen.

| #   | Frage                                                                      | Entscheidung                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | betrifft    |
| --- | -------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------- |
| E1  | Ist die Schusszahl Pflicht?                                                | Pflicht für Mitglieder, mit Obergrenze 999 gegen Tippfehler. Für Gäste optional. In der Datenbank darf sie leer sein, wegen des Altbestands. Gesetzlich verlangt ist sie nicht, sie zählt nie mit.                                                                                                                                                                                                                                                                                              | PR 4a, 5, 6 |
| E2  | Kiosk mit PIN?                                                             | **Mit PIN je Mitglied** (abweichend von der Empfehlung „ohne PIN“). Das Mitglied setzt in seinem Konto eine PIN mit 4 bis 6 Ziffern. Am Kiosk tippt es seinen Namen und seine PIN; der Eintrag trägt dann das Mitglied als „eingetragen von“. Gäste und Mitglieder ohne PIN erfasst die Aufsicht.                                                                                                                                                                                               | PR 6        |
| E3  | Was heißt „erhöhte Admin-Rechte“?                                          | Rolle Admin, dazu erneute Passworteingabe und ein Pflichtgrund. Nie an Zeilen, in denen der Admin selbst Mitglied oder Bestätiger ist. Der Verein braucht deshalb mindestens zwei Admins.                                                                                                                                                                                                                                                                                                       | PR 7        |
| E4  | Reicht zum Starten einer Session die Registerzeile?                        | Ja. Eine eingeteilte Schicht liefert nur die Buchungsliste.                                                                                                                                                                                                                                                                                                                                                                                                                                     | PR 4a       |
| E5  | Bis wann darf die Aufsicht abzeichnen?                                     | Nur am Session-Tag. Der Bestätigungstext nennt die Uhrzeit.                                                                                                                                                                                                                                                                                                                                                                                                                                     | PR 4a       |
| E6  | Wie streng sind die drei Regeln zur Aufsicht?                              | Die Aufsicht ist nie Teilnehmerin ihrer eigenen Session. Wer eine offene Session führt, kann nicht aktiviert werden und trägt nichts ein. Ablehnen ist ein Status in der Vorstufe. **Abweichung:** Die Rechtsprüfung (2f) sah ein Storno in der Kladde vor. Mit dem Status gelangt Abgelehntes nie in die Kladde, und der Vorstand sieht es trotzdem in einer Liste (PR 7). Eine nicht abgezeichnete Session verfällt am Folgetag ohne Nachweis; was zählen soll, kommt als Nachtrag mit Grund. | PR 4a, 7    |
| E7  | Ist das Konto zugleich der Mitgliedsantrag?                                | Ja. Bis zur Aufnahme gibt es keine Anmeldung, nur die Meldung „Antrag liegt beim Vorstand“.                                                                                                                                                                                                                                                                                                                                                                                                     | PR 1, 2     |
| E8  | Wann ist die Selbstregistrierung offen?                                    | Über einen Schalter in den Einstellungen, der anfangs aus ist.                                                                                                                                                                                                                                                                                                                                                                                                                                  | PR 2        |
| E9  | Disziplin: Katalog oder Freitext?                                          | Katalog des Verbands (O1), danach Pflichtfeld. Bis dahin gibt es keine Disziplin und keinen Druck (a). **Ausnahme seit 03.11.2026:** Pflicht nur bei Kurz- und Langwaffe, bei erlaubnisfrei wählbar, weil die BDS-Kennziffernliste keine Luftdruck- und Bogendisziplinen führt.                                                                                                                                                                                                                 | PR 10, 8b   |
| E10 | Haben ausgetretene Mitglieder noch Zugang?                                 | Nein. Den Auszug gibt es beim Austritt, wie heute.                                                                                                                                                                                                                                                                                                                                                                                                                                              | –           |
| E11 | Minderjährige, Vorkommnisse, Aushang (Rest von Q5)                         | Vorerst Papier. Digital nur als eigenes späteres Vorhaben, weil § 27 Abs. 3 WaffG die Erklärung der Sorgeberechtigten verlangt und § 10 Abs. 5 AWaffV eine Jugend-Aufsicht.                                                                                                                                                                                                                                                                                                                     | PRODUCT.md  |
| E12 | Wie werden Minderjährige vom Selbsteintrag ausgeschlossen?                 | „Volljährig geprüft“ wird einmal gespeichert (`users.adult_confirmed_at`), beim Aufnehmen, beim Anlegen oder bei der ersten Aktivierung.                                                                                                                                                                                                                                                                                                                                                        | PR 1, 4a    |
| E13 | Hängt die Session an Aufsicht und Tag statt an einer Buchung?              | Ja. Die Buchung füllt nur die Liste vor. Spontane Schützen ohne Buchung bleiben möglich.                                                                                                                                                                                                                                                                                                                                                                                                        | PR 3, 4a    |
| E14 | Darf die Aufsicht das Session-Protokoll (c) ihrer eigenen Session drucken? | Nein, nur der Admin. Die Aufsicht sieht ihre Session ohnehin am Stand.                                                                                                                                                                                                                                                                                                                                                                                                                          | PR 9        |
| E15 | Steht das Geburtsdatum auf der Mitgliedschaftsbescheinigung?               | Nein, solange Behörde oder Verband es nicht verlangen (Datensparsamkeit). Das Gesetz verlangt es nicht.                                                                                                                                                                                                                                                                                                                                                                                         | PR 8a       |

## Komfortfunktionen

Entschieden am 03.11.2026, jede Funktion einzeln. „Stilllegen“ heißt: Routen, Oberfläche, Navigation und Scheduler-Einträge raus, die Tabellen bleiben stehen. Wiederherstellen lässt sich alles aus der Git-Historie.

| Funktion                                                                  | Entscheidung                                                                                                                                                                                                                                       | Schritt |
| ------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| Gruppen und Freigaben je Schießtag                                        | stilllegen. Vorher auf einer Sicherung prüfen: `SELECT COUNT(*) FROM shooting_days WHERE restricted_to_groups = 1`. Solche Tage sind heute schon unsichtbar, wenn ihre Gruppe gelöscht wurde; beim Stilllegen werden sie wieder für alle sichtbar. | PR K1   |
| Serien von Schießtagen                                                    | Die Verknüpfung (`series`, „auch für Folgetermine“) wird stillgelegt. Die wöchentliche Mehrfachanlage bleibt.                                                                                                                                      | PR K1   |
| CSV-Import der Mitglieder                                                 | stilllegen                                                                                                                                                                                                                                         | PR K2   |
| Erinnerung am Vortag, Kalender-Abo, Mail „Aufsicht gesucht“               | stilllegen                                                                                                                                                                                                                                         | PR K2   |
| Passkeys                                                                  | entfernen                                                                                                                                                                                                                                          | PR K3   |
| Ungenutzte Reste des Starter-Kits (etwa 1060 Zeilen, nirgends importiert) | löschen                                                                                                                                                                                                                                            | PR K3   |
| Statistik für den Vorstand                                                | behalten. Gezählt wird künftig je Person und Tag, nicht je Zeile (PR 3).                                                                                                                                                                           | –       |
| Absage-Mail                                                               | behalten. Sie wirkt erst mit SMTP; bis dahin darf die Meldung nicht „benachrichtigt“ behaupten.                                                                                                                                                    | PR K2   |
| Zwei-Faktor-Anmeldung                                                     | behalten, freiwillig                                                                                                                                                                                                                               | –       |
| Schalter „Gastschützen erlaubt“                                           | behalten. Die Beschriftung stellt klar, dass er nur die Online-Buchung betrifft.                                                                                                                                                                   | PR K1   |
| Polling der Standansicht                                                  | behalten. Mit mehreren schreibenden Geräten ist es nötig.                                                                                                                                                                                          | –       |
| E-Mail-Bestätigung, „Passwort vergessen“, Mail-Status                     | behalten, für die Selbstregistrierung nötig                                                                                                                                                                                                        | –       |
| Profil selbst bearbeiten, eigene Kladde                                   | behalten. Die E-Mail ändert nur der Vorstand (PR 8a).                                                                                                                                                                                              | –       |
| QR-Ankunft „ist da“                                                       | behalten, nur als Hinweis für die Aufsicht. Aktivieren muss die Aufsicht vor Ort, ein Selbstbeitritt per QR ist ausgeschlossen.                                                                                                                    | –       |

Die Schritte K1 bis K3 sind von den Session-Schritten unabhängig und können vorher laufen. Ihre Zeilenangaben beziehen sich auf `6505254`. Seit #51 (Impressum) sind unter anderem Zeilen in `routes/web.php` verschoben.

**Voraussetzung im Betrieb:** SMTP einrichten. Heute gilt `MAIL_MAILER=log`, Mails landen nur im Log. Ohne SMTP gibt es keine Bestätigungslinks, kein „Passwort vergessen“ und keine Absage-Mail. Die Zugangsdaten muss der Verein liefern.

## Rechtsgrundlagen

Abgerufen am 03.11.2026 auf gesetze-im-internet.de: WaffG zuletzt geändert am 22.10.2026, AWaffV zuletzt geändert am 11.07.2025. Vor dem Release erneut prüfen.

| Aussage                                                                                                                                                                                                            | Quelle                                                                                                                                               | Art                                      |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| Der Verein muss die Häufigkeit der Aktivitäten jedes Mitglieds über 24 Monate nachweisen. Wer die Zeile tippt, regelt die Norm nicht.                                                                              | [§ 15 Abs. 1 Nr. 7 b WaffG](https://www.gesetze-im-internet.de/waffg_2002/__15.html)                                                                 | Gesetzestext                             |
| Ein Selbsteintrag ist bis zum Abzeichnen nur eine Eigenangabe, Aufzeichnung des Vereins wird er erst mit dem Abzeichnen.                                                                                           | § 15 Abs. 1 Nr. 7 b, § 14 WaffG                                                                                                                      | Einschätzung                             |
| Gezählt werden Gelegenheiten (Tage), getrennt nach erlaubnispflichtig, Kurz/Lang und eigen/Verein. Eine Schusszahl ist nicht verlangt.                                                                             | [§ 14 Abs. 3 und 4 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html)                                                                   | Gesetzestext                             |
| Schießsport heißt Schießen nach einer genehmigten Sportordnung. Daraus folgt die Disziplin aus dem Katalog.                                                                                                        | [§ 15a Abs. 1 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__15a.html)                                                                       | Gesetzestext, Folgerung als Einschätzung |
| Die Aufsicht muss das Schießen ständig beaufsichtigen. Ohne Aufsicht beginnt kein Schießbetrieb und läuft keiner weiter.                                                                                           | [§ 11 Abs. 1 AWaffV](https://www.gesetze-im-internet.de/awaffv/__11.html), [§ 10 Abs. 1 AWaffV](https://www.gesetze-im-internet.de/awaffv/__10.html) | Gesetzestext                             |
| Ein Verbot, dass die Aufsicht selbst schießt, steht nicht in der AWaffV; die DSB-Schießstandordnung Nr. 12 verbietet es. Daraus folgt: Wer schießt, beaufsichtigt nicht, deshalb kein Abzeichnen eigener Einträge. | § 11 AWaffV; DSB-Schießstandordnung 05/2024                                                                                                          | Verbandspraxis, Einschätzung             |
| Wer allein auf dem Stand schießt, braucht keine Aufsicht. Das läuft nur als Nachtrag mit Grund.                                                                                                                    | [§ 11 Abs. 3 AWaffV](https://www.gesetze-im-internet.de/awaffv/__11.html)                                                                            | Gesetzestext                             |
| Registrierung der Aufsicht beim Verein mit Nachweisdokument und Einblick auf Verlangen                                                                                                                             | [§ 10 Abs. 3 AWaffV](https://www.gesetze-im-internet.de/awaffv/__10.html)                                                                            | Gesetzestext                             |
| Seit 01.01.2026 bescheinigt das Bedürfnis nur der Verband. Der Teilnahmenachweis des Vereins ist keine Bescheinigung nach § 14.                                                                                    | [§ 58 Abs. 21 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__58.html)                                                                        | Gesetzestext                             |
| Die Mitgliedschaftsbescheinigung stellt der Verein aus. Das Gesetz verlangt nur die Mitgliedschaft in einem Verein eines anerkannten Verbands. Ob die 10 Jahre erfüllt sind, entscheidet die Behörde.              | [§ 14 Abs. 4 S. 3 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html)                                                                    | Gesetzestext                             |
| Minderjährige schießen nur unter Obhut einer geeigneten Aufsicht und mit Einverständnis der Sorgeberechtigten.                                                                                                     | [§ 27 Abs. 3 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__27.html), § 10 Abs. 5 AWaffV                                                     | Gesetzestext                             |
| Eine Pflicht, Gäste aufzuzeichnen, findet sich nicht.                                                                                                                                                              | § 12 WaffG, § 9 AWaffV                                                                                                                               | Gesetzestext                             |
| Datenschutz: Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO (Antrag, Mitgliedschaft), keine Einwilligung. Beim Registrieren gibt es einen Hinweis nach Art. 13.                                                    | DSGVO                                                                                                                                                | Einschätzung, nicht abgerufen            |

## Fragen an Verband, Behörde und Pilotverein

- **Verband:**
    - Erkennt er Selbsteintrag plus Abzeichnen durch die Aufsicht als Grundlage seiner Bescheinigung an?
    - Genügt eine digitale Abzeichnung, oder braucht es den gedruckten Auszug mit Unterschrift und Stempel (O2)?
    - Gibt es ein Kriterium für „Disziplin vollständig geschossen“, etwa eine Mindestschusszahl wie beim VdRBw? Verlangt er Schusszahl oder Kaliber?
    - Welcher Disziplinkatalog mit welchem Nummernformat gilt? Gibt es ein Formular für den Schießnachweis (O1)? **Beantwortet:** BDS-Kennziffer (vierstellig, Steel Challenge fünfstellig), Befürwortungsformular 1/2026 des LV 3.
    - Zählt ein Tag mit mehreren Disziplinen als ein Termin je Kategorie? Wie sind „alle drei Monate“ und „abgeschlossener Zeitraum“ zu lesen (O5)?
    - Gehören erlaubnisfreie Termine in den Nachweis (O15)? Zählen Termine bei fremden Vereinen (O6)?
    - Gilt „Aufsicht schießt nicht“ auch in diesem Verband? Gibt es Regeln für den Wechsel zwischen Aufsichten?
- **Behörde oder Verband:**
    - Gibt es ein Muster für die Mitgliedschaftsbescheinigung?
    - Darf das Nachweisdokument der Aufsicht digital sein (O7)?
    - Gilt eine elektronisch gespeicherte Erklärung der Sorgeberechtigten als „schriftlich“ (O8)?
- **Pilotverein:** Verlangt der Erlaubnisbescheid der Schießstätte ein Standbuch, ein Session-Protokoll oder eine Mindestzahl an Aufsichten (O3)?

## Bauplan

### Zielbild

Ein selbst angelegtes Konto ist der Mitgliedsantrag (`role=applicant` und `active=false`). Anmelden kann man erst nach der Aufnahme durch den Vorstand, damit greifen alle vorhandenen `active`-Sperren. Die Schießaufsicht bleibt die Registerzeile in `qualifications`. Sie startet eine Schieß-Session (`shooting_sessions`) und aktiviert X, Y, Z vor Ort. Diese erfassen Disziplin, Schüsse, Kategorie und eigen/Verein am eigenen Handy oder am Kiosk-Tablet.

Bis zum Abzeichnen liegen die Einträge ohne Nachweiswert in der Vorstufe `session_entries`. Sie werden nicht bearbeitet, nur zurückgezogen oder abgelehnt. Das Abzeichnen schreibt genau die Einträge, die die Aufsicht im Dialog gesehen hat, insert-only in die Kladde `log_entries` (`recorded_by_id` = Aufsicht, `shot_on` = Session-Tag). Danach ändert nur ein Admin, per Storno oder verknüpfter Korrektur, mit Passwort und Grund und nie an eigenen Zeilen. Nicht abgezeichnete Sessions verfallen am Folgetag ohne Nachweis; zählen kann dann nur ein Nachtrag mit Grund.

Planung, Buchung, Register, Nachtrag, Auszug und Bescheinigung bleiben (Q7 = B). Der Umbau läuft in 13 PR-Schritten, dazu kommen K1 bis K3 für die Komfortfunktionen; die Umstellung am Stand (PR 4b) kommt außerhalb eines Schießtags.

### Ablauf am Stand

1. Vorher hat das Mitglied entweder ein Konto (selbst registriert und vom Vorstand aufgenommen, oder vom Verein angelegt) oder keines (K4). Eine Bahnbuchung ist möglich, aber nicht nötig, und hat keinen Nachweiswert (Q2 = A).
2. Die Aufsicht meldet sich an, öffnet /stand und tippt „Session starten“.
   Der Server prüft in einer Transaktion:
    - Registerzeile am heutigen Tag, auch bei Admins;
    - höchstens eine offene Session je Aufsicht und Tag;
    - Datum = heute, vom Server gesetzt.
      Der Umfang laut Register legt die erlaubten Kategorien fest. Eine eingeteilte Schicht ist nicht nötig; ohne Schicht zeigt /stand keine Buchungsliste, nur die Mitgliedersuche.
3. Die Aufsicht aktiviert X, Y und Z vor Ort einzeln:
    - „Aktivieren“ in der Buchungsliste ihrer Stände. Das geht unabhängig vom Buchungsstatus, auch bei einer schon auf `checked_in` stehenden Buchung (heute früher Return in app/Domain/Bookings.php:136-137). Der Buchungsstatus ist nur ein Label.
    - Mitgliedersuche nach Name oder Mitgliedsnummer (nur aktive Mitglieder).
    - Gast mit Name und Pflichthaken „volljährig“.
      Fehlt bei einem Mitglied `adult_confirmed_at`, verlangt das Aktivieren einmalig den Haken „volljährig geprüft“. Er wird gespeichert und protokolliert.
      Abgewiesen werden: die Aufsicht selbst und wer heute selbst eine offene Session führt.
4. X erfasst am eigenen Handy. Einziger Einstieg ist die Karte „Heute schießen“ auf dem Dashboard, sichtbar nach der Aktivierung.
    - Felder: Disziplin (nur mit Katalog), Schüsse, Kategorie, eigen/Verein; vorbelegt aus dem letzten Eintrag.
    - Mehrere Disziplinen ergeben mehrere Einträge.
    - Wurde X heute mehrfach aktiviert (Standwechsel), schreibt das Handy in die jüngste Aktivierung.
    - Bei einem gleichen Eintrag kommt der Hinweis „schon erfasst (n Schüsse) – zurückziehen und neu erfassen?“; nichts wird still überschrieben.
5. Y erfasst am Kiosk-Tablet.
   Die Aufsicht wählt in /stand „Kiosk“:
    - Link/QR für ein anderes Gerät, oder
    - „Kiosk auf diesem Gerät“: meldet nur dieses Gerät ab (`logoutCurrentDevice`) und öffnet den Kiosk-Link.
      Der Kiosk läuft ohne Anmeldung:
    - nur die Teilnehmerliste dieser Session mit Name und Mitgliedsnummer;
    - Name antippen → eigene PIN eingeben → Formular → grüne Karte → zurück zur Liste;
    - nur Anlegen, kein Zurückziehen.
      Er endet mit dem Abzeichnen oder dem Datumswechsel. Zurück zur Aufsicht geht es über die normale Anmeldung. Gäste und Mitglieder ohne PIN (auch ohne Konto) erfasst die Aufsicht (`via=stand`).
6. Der Server prüft jeden Eintrag in einer Transaktion:
    - Teilnehmer einer offenen Session von heute, jüngste Aktivierung;
    - der Teilnehmer führt nicht selbst gerade eine offene Session (wer beaufsichtigt, schießt nicht);
    - am eigenen Gerät nur für sich, am Kiosk nur für Teilnehmer dieser Session;
    - Disziplin aus dem Katalog, falls einer gewählt ist; sonst bleibt sie leer;
    - Kategorie und eigen/Verein sind Pflicht (§ 14 Abs. 3/4 WaffG);
    - Kurz/Lang nur bei Umfang `licensed` der Inhaberin;
    - gleiche Disziplin, Kategorie und eigen/Verein heute schon in der Kladde → abgewiesen;
    - ein Doppel-Tap liefert denselben Eintrag.
7. Bis zum Abzeichnen:
    - Bearbeitet wird nichts.
    - Zurückziehen darf, wer erfasst hat: das Mitglied am eigenen Gerät, die Aufsicht ihre `stand`-Einträge.
    - Ablehnen mit Grund darf nur die Inhaberin, für jeden Eintrag, auch vom Kiosk.
    - Das Mitglied sieht die Ablehnung samt Grund und erfasst neu.
    - Die Aufsicht sieht alle Einträge mit „erfasst um“ und „erfasst über“; Polling alle 30 s.
8. Aufsichtswechsel: A zeichnet ihre Session ab, B startet eine eigene und aktiviert die Verbliebenen neu, auch mit schon aktivierter Buchung.
    - Solange A eine offene Session führt, weist das Aktivieren von A ab.
    - Wer selbst eine offene Session führt, kann in keiner Session neue Einträge anlegen.
      Damit kann keine Aufsicht gleichzeitig schießen und beaufsichtigen, und zwei Aufsichten können sich nicht gegenseitig abzeichnen.
9. Abzeichnen:
   Der Dialog listet jeden offenen Eintrag mit „erfasst um“ und „erfasst über“, dazu die Teilnehmer ohne Eintrag. Die Aufsicht bestätigt: „Die aufgeführten Personen haben heute bis HH:MM unter meiner Aufsicht geschossen.“ Der Request trägt die IDs der angezeigten Einträge.
   Der Server prüft in der IMMEDIATE-Transaktion (config/database.php:45):
    - Inhaberin, heute = `shot_on`;
    - Registerzeile noch gültig, Umfang passt;
    - angezeigte IDs = aktuelle `pending`-IDs, sonst „Liste hat sich geändert, bitte neu prüfen“.
      Dann schreibt er:
    - je Eintrag eine Kladde-Zeile (`source=session`, `recorded_by_id` = Aufsicht, `shot_on`, `shots`, `session_entry_id`);
    - danach die Einträge auf `signed`, danach die Session.
      Ein zweites Abzeichnen ändert nichts. Oberfläche und Hilfe sagen: „Vor dem Verlassen des Stands abzeichnen.“
10. Nach dem Abzeichnen sperren Trigger die Session, ihre Teilnehmer und ihre Einträge, auch gegen neue INSERTs. Änderungen macht nur der Admin: Storno oder Korrektur mit Grund, nach erneuter Passworteingabe, nie an Zeilen mit eigener Person als Mitglied oder Bestätiger.
11. Wird bis 23:59 nicht abgezeichnet, ist die Session „verfallen“. Das wird berechnet, es gibt keinen Cron. Es entsteht keine Kladde-Zeile und damit kein Nachweis. Der Admin sieht verfallene Sessions in der Kladde des Vereins; was trotzdem zählen soll, trägt er als gekennzeichneten Nachtrag mit Grund ein (K9). Ebenso läuft „§ 11 Abs. 3 AWaffV, allein auf dem Stand“ nur als Nachtrag. Wird der Aufsicht während der Session das Register entzogen, wird das Abzeichnen abgewiesen und die Session verfällt.
12. Das Mitglied sieht jederzeit:

- in „Meine Kladde“: Tage, Disziplin, Schüsse, bestätigt von, erfasst über, dazu den Druck (b) und, sobald ein Katalog gewählt ist, je Disziplin (a) „zur Information“;
- in „Meine Buchungen“: gebuchte bzw. vorgemerkte Bahnen mit Status;
- unter „Heute schießen“: die eigenen offenen Einträge mit Status und Ablehnungsgrund.

13. Bei einem Ausfall gilt die Papier-Ersatzliste, jetzt mit Spalte Schüsse. Der Admin überträgt sie als Nachtrag.

### Datenmodell

#### Bestehend

**`users`**

- `role` bekommt den Wert `applicant`. Die Spalte ist ein String mit DB-Default `member`; Bestand und Factory bleiben Mitglied.
- Ein Antrag ist `role=applicant` **und** `active=false`. Damit greifen die vorhandenen Sperren ohne neue Middleware:
    - Login (app/Providers/FortifyServiceProvider.php:51)
    - laufende Sitzung (app/Http/Middleware/LogoutInactiveUsers.php:22)
    - `canSupervise` (app/Models/User.php:89)
    - `Logbook::record` (app/Domain/Logbook.php:61)
    - alle Listen mit `where('active', true)`
- Neu: `kiosk_pin_hash` string NULL. Das ist der Hash der Kiosk-PIN (E2). Das Mitglied setzt sie in seinem Konto, der Vorstand kann sie nur löschen, nie sehen.
- Neu: `adult_confirmed_at` timestamp NULL, die einmalige Bestätigung „volljährig geprüft“. Blueprint-Spalte ohne `constrained()`; `users` hat keine Trigger, ein Neubau entfällt. Wer bestätigt hat, steht in `audit_entries`.

**`qualifications`, `shooting_days`, `supervisions`, `bookings`**

- unverändert
- `bookings.status = checked_in` heißt künftig „aktiviert“ (nur das Label).

#### Neu: `shooting_sessions`

Der Name `sessions` ist durch Laravels Sitzungsspeicher belegt (database/migrations/0001_01_01_000000_create_users_table.php:30).

| Feld             | Typ / Regel                                                                |
| ---------------- | -------------------------------------------------------------------------- |
| id               |                                                                            |
| supervisor_id    | FK users, restrict; die einzige Person, die abzeichnet                     |
| shot_on          | date, beim Start = heute (Server); wird `log_entries.shot_on`              |
| status           | `open` oder `signed`                                                       |
| signed_at        | timestamp, null                                                            |
| kiosk_token_hash | string(64), null; sha256 des Kiosk-Links; ein neuer Link ersetzt den alten |
| open_key         | `storedAs case when status='open' then 1 end` (neue Tabelle, kein Neubau)  |
| timestamps       | created_at = gestartet um                                                  |

- Index unique(supervisor_id, shot_on, open_key): je Aufsicht und Tag höchstens eine offene Session. Nach dem Abzeichnen kann sie am selben Tag eine neue starten.
- „Verfallen“ heißt `open` mit `shot_on` < heute. Das berechnet die App; im Trigger steht kein `date('now')`, weil SQLite dort UTC rechnet.
- Kein FK auf `supervisions` oder `shooting_days`: Schichten werden hart gelöscht (app/Domain/Bookings.php:374, Kaskade database/migrations/2026_11_03_000200_create_supervisions_and_bookings.php:17).
- Trigger (SQLite und MySQL):
    - `shooting_sessions_no_delete`
    - `shooting_sessions_final`: BEFORE UPDATE WHEN OLD.status <> 'open' OR NEW.status NOT IN ('open','signed') OR supervisor_id, shot_on oder created_at geändert.
- Weggelassen: `opened_at` (= created_at), Status `closed`.

#### Neu: `session_participants`

| Feld       | Typ / Regel                     |
| ---------- | ------------------------------- |
| id         |                                 |
| session_id | FK shooting_sessions, restrict  |
| user_id    | FK users, restrict, null        |
| guest_name | null                            |
| booking_id | FK bookings, nullOnDelete, null |
| timestamps | created_at = aktiviert um       |

- Unique(session_id, user_id) und unique(session_id, guest_name).
- Trigger:
    - `session_participants_insert_open`: BEFORE INSERT, Abbruch, wenn die Session nicht `open` ist.
    - `session_participants_frozen`: BEFORE UPDATE, Abbruch bei jeder Änderung außer `booking_id` → NULL (nullOnDelete).
    - `session_participants_delete_open`: BEFORE DELETE, Abbruch, wenn die Session nicht `open` ist. Mit Einträgen verhindert schon FK restrict das Löschen.
- In der App, in derselben Transaktion geprüft:
    - user_id ≠ supervisor_id
    - nur aktive Mitglieder
    - nicht, wer heute selbst eine offene Session führt
    - `adult_confirmed_at` gesetzt, sonst Haken nötig
- Weggelassen: `activated_by_id` (immer = supervisor_id).

#### Neu: `session_entries` (Vorstufe, ohne Nachweiswert)

| Feld                          | Typ / Regel                                                                             |
| ----------------------------- | --------------------------------------------------------------------------------------- |
| id                            |                                                                                         |
| participant_id                | FK session_participants, restrict                                                       |
| discipline                    | string NOT NULL default '' ('' = kein Katalog)                                          |
| weapon_class                  | short, long oder exempt                                                                 |
| weapon_owner                  | string NOT NULL default '' (own oder club; '' bei Gästen)                               |
| shots                         | unsigned int, null                                                                      |
| via                           | `own_device`, `kiosk` oder `stand`                                                      |
| entered_by_id                 | FK users, null: Mitglied am eigenen Gerät und am Kiosk (nach PIN), Aufsicht bei `stand` |
| status                        | `pending`, `withdrawn`, `rejected` oder `signed`                                        |
| reject_reason, rejected_by_id | Pflicht bei `rejected`                                                                  |
| timestamps                    | created_at = erfasst um                                                                 |
| open_key                      | `storedAs case when status in ('pending','signed') then 1 end`                          |

- Unique(participant_id, discipline, weapon_class, weapon_owner, open_key) gegen Doppel-Tap. Weil '' statt NULL gespeichert wird, wirkt er auf SQLite und MySQL gleich, auch für Gäste. Das Model bildet '' auf null ab.
- Trigger:
    - `session_entries_no_delete`
    - `session_entries_insert_open`: BEFORE INSERT, Abbruch, wenn die Session des Teilnehmers nicht `open` ist.
    - `session_entries_final`: BEFORE UPDATE WHEN OLD.status <> 'pending' OR NEW.status nicht erlaubt OR eine Spalte außer status, reject_reason, rejected_by_id, updated_at geändert OR Session nicht `open`.
- Folgen: Ein Bearbeiten gibt es nicht, und `signOff` setzt zuerst die Einträge und erst dann die Session auf `signed`.

#### Geändert: `log_entries`

Eine Migration, die sich selbst in `DB::transaction()` fasst, mit Guards (`Schema::hasColumn`, `DROP INDEX/TRIGGER IF EXISTS`).

**Neue Spalten.** Auf SQLite nur per rohem `ALTER TABLE … ADD COLUMN`: `constrained()` würde die Tabelle neu bauen und die Trigger löschen. Auf MySQL Blueprint mit `constrained()`.

- `shots INTEGER NULL`: nie im Schlüssel, nie in der Zählung
- `session_entry_id INTEGER NULL REFERENCES session_entries(id)`: Herkunft (wer, wann, über welches Gerät, welche Session)
- `corrects_id INTEGER NULL REFERENCES log_entries(id)`: Korrektur durch den Admin

**Bedeutung unverändert:** `recorded_by_id` = wer bestätigt hat.

- Session: die abzeichnende Aufsicht
- Altbestand `stand`: die eintragende Aufsicht
- Nachtrag und Korrektur: der Admin

Session-Zeilen haben `booking_id` NULL.

**`source`:**

- `stand`: Altbestand
- `session`: neu
- `late_entry`: Nachtrag; mit `corrects_id` ist es eine Korrektur.

**Indizes:**

- `log_entries_member_unique` neu: (user_id, shot_on, weapon_class, weapon_owner, coalesce(discipline,''), active_key). Er bekommt nur eine Spalte dazu, Bestandszeilen können ihn also nicht verletzen.
- Neu `log_entries_session_entry_unique`: UNIQUE(session_entry_id). NULL kollidiert weder in SQLite noch in MySQL. Das macht das Abzeichnen idempotent, auch gegen direkte Inserts.
- `log_entries_guest_unique` (guest_name, shot_on, active_key) bleibt unverändert. Gäste zählen nicht; eine zweite Kategorie eines Gastes am selben Tag ist ein Duplikat.
- MySQL/MariaDB: Member-Index ohne coalesce. Er schützt schwächer, `findActive` prüft weiter; ungetestet.

**Wirkung:**

- Zwei Disziplinen am selben Tag ergeben zwei Zeilen. Der Doppelschutz K5 gilt für Stand, Session und Nachtrag gemeinsam.
- Gleiche Disziplin, Kategorie und eigen/Verein zweimal am selben Tag (zweite Session oder Nachtrag) ergibt eine Zeile.
    - `stage` weist das ab: „heute schon in der Kladde“.
    - Trifft es erst beim Abzeichnen (Race), wird der Eintrag `signed`, aber ohne eigene Kladde-Zeile. Seine Schüsse stehen dann nur in `session_entries`.

**Trigger:** DROP und CREATE `log_entries_only_cancel` (beide Zweige).

- WHEN-Liste: alle Spalten außer CANCEL_FIELDS (status, cancel_reason, cancelled_by_id, cancelled_at, updated_at, active_key). Neu dabei: id, shots, session_entry_id, corrects_id.
- Zusätzlich verboten: `NEW.status NOT IN ('active','cancelled')` und Storno-Felder ohne `NEW.status = 'cancelled'`.

**Model `LogEntry`:** Der Deny-Diff (app/Models/LogEntry.php:56-66) bleibt. Dazu kommen der Cast `shots`, die Relationen `sessionEntry` und `corrects` und der Docblock „bestätigt von“. `findActive` (app/Domain/Logbook.php:252) vergleicht zusätzlich `coalesce(discipline,'')`.

#### Enums und Einstellungen

- `Role`: + `Applicant`
- `LogSource`: + `Session`
- neu: `EntryVia`, `SessionStatus`, `SessionEntryStatus`
- `ClubSettings::KEYS` + `registration_open` => 'int', Werte 0/1. KEYS kennt nur string und int (app/Support/ClubSettings.php:21). Default 0 in config/verein.php.
- `discipline_catalog` kommt in PR 10 in KEYS; config/verein.php:26 hat den Schlüssel schon.

### Zustände

**Session (`shooting_sessions.status`)**

- – → `open`: Die Aufsicht startet sie. Voraussetzung: Registerzeile am Tag, auch bei Admins. Je Aufsicht und Tag höchstens eine offene Session (Index). `shot_on` = heute, vom Server gesetzt.
- `open` → `signed`: nur die Inhaberin. Bedingungen:
    - heute = `shot_on`;
    - Registerzeile noch gültig;
    - Kurz/Lang nur mit Umfang `licensed`;
    - angezeigte Eintrags-IDs = aktuelle `pending`-IDs.
      Schreibt die Kladde-Zeilen, setzt erst die Einträge, dann die Session. Endgültig.
- `open` mit `shot_on` < heute = „verfallen“. Das ist kein eigener Status, sondern berechnet; es gibt keinen Cron. Erfassen, Ablehnen und Abzeichnen sind gesperrt, und es gibt keinen Nachweis. Die Einträge bleiben `pending` ohne Wert. Zählen kann nur ein Nachtrag des Admins mit Grund.
- `signed`: Ein Trigger verbietet jedes UPDATE, gelöscht wird nie.

**Kiosk-Link (`kiosk_token_hash`)**

- – → gesetzt: Die Inhaberin erzeugt den Link, nur bei `open` und am Session-Tag. Ein neuer Link ersetzt den alten.
- Gültig nur bei `open` und `shot_on` = heute. Er erlischt mit dem Abzeichnen oder dem Datumswechsel.

**Teilnahme (`session_participants`)**

- – → aktiviert: durch die Inhaberin vor Ort, auf drei Wegen:
    - aus einer Buchung, unabhängig von deren Status. `booked`/`no_show` → `checked_in` als Label; bei `no_show` zuerst `ensureLaneIsFreeAgain`.
    - über die Suche (aktive Mitglieder)
    - als Gast (Name und Haken „volljährig“)
- Abgewiesen werden:
    - die Inhaberin selbst
    - wer heute selbst eine offene Session führt
    - Anträge und Deaktivierte
    - ein Mitglied ohne `adult_confirmed_at`, solange der Haken fehlt; mit Haken wird der Wert gespeichert.
- Mehrfach aktiviert am selben Tag: neue Einträge nur in der jüngsten Aktivierung.
- aktiviert → entfernt: nur die Inhaberin, nur bei offener Session und ohne Einträge. Die Buchung geht zurück auf `booked`, wenn keine andere Teilnahme sie nennt.
- Nach dem Abzeichnen ist die Teilnahme eingefroren (Trigger). Erlaubt bleibt nur `booking_id` → NULL, wenn die Buchung gelöscht wird.

**Eintrag in der Vorstufe (`session_entries.status`)**

- – → `pending`: erfasst
    - vom Mitglied am eigenen Gerät (`own_device`, nur für sich),
    - am Kiosk (`kiosk`, `entered_by_id` = Mitglied nach richtiger PIN),
    - oder von der Aufsicht (`stand`).
      Das geht nur, solange der Teilnehmer nicht selbst eine offene Session führt.
- `pending` → `pending` gibt es nicht; bearbeitet wird nicht.
- `pending` → `withdrawn`: durch die Person, die erfasst hat (`entered_by_id` = angemeldete Person). Kiosk-Einträge nimmt man nicht zurück, sie werden abgelehnt.
- `pending` → `rejected`: nur die Inhaberin, Grund ist Pflicht. Das Mitglied sieht den Grund und erfasst neu.
- `pending` → `signed`: nur durch das Abzeichnen der Session.
- `withdrawn`, `rejected`, `signed`: endgültig (Trigger).

**Kladde-Zeile (`log_entries.status`)**

- – → `active` entsteht auf vier Wegen:
    - beim Abzeichnen (`source=session`)
    - als Nachtrag des Admins (`late_entry`, mit Grund)
    - als Korrektur (`late_entry` mit `corrects_id`)
    - Altbestand (`stand`)
- `active` → `cancelled`: nur der Admin, mit Grund und Passwortbestätigung. Nie an Zeilen, bei denen er selbst Mitglied (`user_id`) oder Bestätiger (`recorded_by_id`) ist. Nur einmal möglich (Trigger).
- Korrektur: Storno und neue Zeile in derselben Transaktion.
    - Geändert werden dürfen nur Kategorie, eigen/Verein, Disziplin und Schüsse, nie Person oder Datum; dafür gibt es Storno plus Nachtrag.
    - Kurz/Lang nur, wenn die Ursprungszeile schon Kurz/Lang war.

**Buchung (`bookings.status`)**

- `booked` → `checked_in` (= aktiviert), `no_show` oder `cancelled`.
- `no_show` → `booked`: „zurücknehmen“; prüft Bahn und Aufsicht neu, wie heute.
- `no_show` → `checked_in`: über das Aktivieren, nach `ensureLaneIsFreeAgain`.
- `checked_in` → `booked`: nur wenn der Teilnehmer ohne Einträge entfernt wird und keine andere Teilnahme die Buchung nennt.

**Konto und Mitgliedschaft (`users.role`, `users.active`)**

- – → `applicant` mit `active=false`: Selbstregistrierung, nur bei offenem Schalter. Danach wird sofort abgemeldet, mit der Meldung „Antrag liegt beim Vorstand“.
- `applicant` → `member` mit `active=true`: Admin „Aufnehmen“ mit dem Haken „Identität und Volljährigkeit geprüft“. Dabei werden gesetzt: Mitgliedsnummer, „Mitglied seit“ (Default heute), `email_verified_at`, `adult_confirmed_at`; dazu ein Audit-Eintrag.
- `applicant` → gelöscht: durch Admin „Ablehnen“ oder den Löschlauf (nur `role=applicant`, älter als 30 Tage).
- Gibt es die Person schon als Datensatz (z. B. ohne E-Mail), dann:
    1. Antrag ablehnen;
    2. E-Mail am bestehenden Datensatz eintragen;
    3. Einladungslink nach Prüfung der Identität persönlich übergeben.
       Ein „Zuordnen“ gibt es nicht.
- – → `member`: Der Verein legt an (Formular; der Import entfällt mit K2), mit oder ohne Konto.
- `member` ↔ `admin`: durch den Admin.
- `active=false` bei `member`: Austritt bzw. Deaktivierung. Die Kladde-Zeilen bleiben erhalten.
- Das Bearbeiten-Formular ist für Anträge gesperrt.

### Rechte

„Gast“ meint hier einen Gastschützen ohne Konto bzw. jemanden, der nicht angemeldet ist.
„Mitglied (beantragt)“ ist ein Konto mit `role=applicant` und `active=false`.
„Aufsicht“ ist ein Mitglied mit gültiger Registerzeile am Tag.
„Admin“ ist ein Mitglied mit der Rolle admin. Für Aufgaben am Stand braucht auch ein Admin eine Registerzeile.

| Aktion                                                                                                          | Gast                                                     | Mitglied (beantragt)                       | Mitglied                                                                       | Aufsicht                                                                                             | Admin                                                                                                                              |
| --------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------- | ------------------------------------------ | ------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Konto anlegen (= Mitgliedsantrag)                                                                               | ja, wenn der Schalter in den Einstellungen an ist        | –                                          | –                                                                              | –                                                                                                    | –                                                                                                                                  |
| Anmelden                                                                                                        | nein                                                     | nein; Meldung „Antrag liegt beim Vorstand“ | ja                                                                             | ja                                                                                                   | ja                                                                                                                                 |
| Bahn buchen, Meine Buchungen                                                                                    | nein (nur über einen Gastgeber)                          | nein                                       | ja                                                                             | ja                                                                                                   | ja                                                                                                                                 |
| Eigene Kladde, Auszug (b), Teilnahmenachweis (a) „zur Information“                                              | nein                                                     | nein                                       | ja, nur eigene                                                                 | ja, nur eigene                                                                                       | ja                                                                                                                                 |
| Teilnehmer einer Session sein                                                                                   | ja, mit Name und „volljährig“ von der Aufsicht aktiviert | nein                                       | ja, nach Aktivierung vor Ort; einmal „volljährig geprüft“                      | ja, nur in der Session einer anderen Aufsicht und nur, solange sie selbst keine offene Session führt | wie Mitglied bzw. Aufsicht                                                                                                         |
| Eintrag am eigenen Gerät                                                                                        | nein                                                     | nein                                       | nur für sich; jüngste Aktivierung; offene Session von heute                    | wie Mitglied, nicht während einer eigenen offenen Session                                            | wie Mitglied                                                                                                                       |
| Eintrag am Kiosk (ohne Anmeldung)                                                                               | nein, erfasst die Aufsicht                               | nein                                       | ja: Name aus der Teilnehmerliste und eigene PIN; ohne PIN erfasst die Aufsicht | –                                                                                                    | –                                                                                                                                  |
| Offenen Eintrag bearbeiten                                                                                      | niemand: zurückziehen und neu erfassen                   |                                            |                                                                                |                                                                                                      |                                                                                                                                    |
| Offenen Eintrag zurückziehen                                                                                    | –                                                        | –                                          | eigene Erfassung am eigenen Gerät                                              | eigene Erfassungen am Stand (`stand`)                                                                | –                                                                                                                                  |
| Session starten, Teilnehmer aktivieren oder entfernen, Kiosk-Link erzeugen                                      | –                                                        | –                                          | –                                                                              | ja: Registerzeile am Tag; Kurz/Lang nur mit `licensed`; nie sich selbst als Teilnehmer               | nur mit eigener Registerzeile (das Tor gilt auch für Admins)                                                                       |
| Einzelne Einträge mit Grund ablehnen, Session abzeichnen                                                        | –                                                        | –                                          | –                                                                              | nur die Inhaberin, nur am Session-Tag                                                                | nur als Inhaberin                                                                                                                  |
| Verfallene Sessions, abgelehnte und zurückgezogene Einträge einsehen                                            | –                                                        | –                                          | eigene Einträge                                                                | eigene Session am Tag                                                                                | ja, alle                                                                                                                           |
| Kladde des Vereins einsehen, CSV                                                                                | –                                                        | –                                          | –                                                                              | –                                                                                                    | ja                                                                                                                                 |
| Nachtrag, Storno, Korrektur                                                                                     | –                                                        | –                                          | –                                                                              | –                                                                                                    | ja: Passwortbestätigung, Pflichtgrund; nie an Zeilen mit eigener Person als Mitglied oder Bestätiger (braucht einen zweiten Admin) |
| Antrag aufnehmen (Identität und Volljährigkeit geprüft) oder ablehnen; Konten anlegen; Link zum Passwort setzen | –                                                        | –                                          | –                                                                              | –                                                                                                    | ja                                                                                                                                 |
| Aufsichtsregister pflegen                                                                                       | –                                                        | –                                          | –                                                                              | –                                                                                                    | ja, nie für sich selbst (UserController.php:161)                                                                                   |
| Drucke „zur Vorlage“ (a), (b), Bescheinigung (d), Papierliste, Session-Protokoll (c)                            | –                                                        | –                                          | –                                                                              | (c) für die eigene Session: nein (E14)                                                               | ja; (d) nie für Anträge                                                                                                            |

### Invarianten als Tests

- Eine Kladde-Zeile mit `source=session` entsteht nur durch das Abzeichnen. Abgelehnte, zurückgezogene und verfallene Einträge erscheinen weder in der Kladde noch im Auszug, im Teilnahmenachweis oder in der Statistik.
- `shot_on` einer Session-Zeile ist immer `shooting_sessions.shot_on`. Abzeichnen nach Mitternacht wird abgewiesen (Test mit `travelTo`).
- Das Abzeichnen schreibt genau die angezeigten Einträge. Kommt nach dem Laden des Dialogs ein Eintrag hinzu oder wird einer zurückgezogen, wird das Abzeichnen abgewiesen, und es entsteht keine Kladde-Zeile.
- Kein gegenseitiges Abzeichnen:
- `recorded_by_id` einer Session-Zeile ist die Inhaberin.
- Die Inhaberin kann nicht Teilnehmerin ihrer eigenen Session werden.
- Wer heute eine offene Session führt, kann nicht aktiviert werden und legt in keiner Session neue Einträge an.
  Getestet auch mit zwei Aufsichten, die sich in schnellem Wechsel gegenseitig aktivieren wollen.
- Starten und Abzeichnen kann nur, wer am Tag eine gültige Registerzeile hat, auch ein Admin. Wird die Registrierung während der Session entzogen, wird das Abzeichnen abgewiesen; die Einträge bleiben `pending`, die Session verfällt.
- Kurz- und Langwaffe nur, wenn die Inhaberin den Umfang `licensed` hat. Geprüft wird beim Erfassen und noch einmal beim Abzeichnen.
- Am eigenen Gerät trägt ein Mitglied nur für sich ein, nur in seiner jüngsten Aktivierung von heute und nur bei offener Session. Wer nicht aktiviert, Antragsteller oder deaktiviert ist, wird abgewiesen.
- Kiosk:
- ohne Token, mit ersetztem Token, nach dem Abzeichnen oder am Folgetag: abgewiesen;
- zeigt nur Teilnehmer dieser Session, mit Name und Mitgliedsnummer;
- erlaubt nur Anlegen;
- ist für angemeldete Nutzer nicht erreichbar (`guest`);
- falsche PIN: abgewiesen. Nach 5 Fehlversuchen je Teilnehmer und Session ist der Kiosk für diese Person 15 min gesperrt. Ohne gesetzte PIN gibt es keinen Kiosk-Eintrag. Die PIN liegt nie im Klartext in DB, Log oder Antwort;
- „Kiosk auf diesem Gerät“ meldet nur dieses Gerät ab; das Remember-Token des Kontos bleibt gültig.
- Ein offener Eintrag lässt sich nicht bearbeiten: Jedes UPDATE außer dem Statuswechsel wirft (Trigger). Zurückziehen darf nur, wer erfasst hat. Ablehnen darf nur die Inhaberin, und nur mit Grund.
- Doppel-Tap:
- Zwei gleiche Erfassungen ergeben genau einen `pending`-Eintrag; die Antwort meldet „schon erfasst“ mit der Schusszahl.
- Zweimal abzeichnen ergibt keine zweite Kladde-Zeile.
- Ein direkter Insert mit derselben `session_entry_id` scheitert an `log_entries_session_entry_unique`.
- Zwei Disziplinen am selben Tag ergeben zwei Kladde-Zeilen, `ShootingRecord::totals` zählt den Tag einmal. Gleiche Disziplin, Kategorie und eigen/Verein in einer zweiten Session desselben Tages weist `stage` ab. Im Race beim Abzeichnen wird der Eintrag `signed`, es bleibt aber bei einer Kladde-Zeile.
- Ohne Katalog wird `discipline` als NULL gespeichert, das Feld ist ausgeblendet, und der Doppelschutz greift (`coalesce`). Mit Katalog sind nur Schlüssel aus dem Katalog erlaubt.
- Generischer Trigger-Test: Für jede Spalte aus `Schema::getColumnListing('log_entries')` außer CANCEL_FIELDS wirft ein Query-Builder-UPDATE eine QueryException. Ebenso werfen: `status` auf einen anderen Wert als `cancelled`, Storno-Felder ohne `cancelled`, ein zweites Storno und DELETE.
- Nach `migrate` stehen alle erwarteten Trigger in `sqlite_master`:
- `log_entries_no_delete`, `log_entries_only_cancel`
- `qualifications_no_update`, `qualifications_no_delete`, `bookings_keep_logged`
- `shooting_sessions_no_delete`, `shooting_sessions_final`
- `session_participants_insert_open`, `session_participants_frozen`, `session_participants_delete_open`
- `session_entries_no_delete`, `session_entries_insert_open`, `session_entries_final`
  Dazu `log_entries_member_unique` (mit coalesce) und `log_entries_session_entry_unique`. Ein erneuter Lauf der Migrationen nach einem Abbruch scheitert nicht (Guards).
- Für eine abgezeichnete Session werfen über den Query Builder:
- jedes UPDATE und DELETE auf Session, Teilnehmer und Einträge;
- jedes INSERT neuer Teilnehmer oder Einträge.
  Erlaubt bleibt nur `booking_id` → NULL an einer Teilnahme, wenn die Buchung gelöscht wird.
- Die Aufsicht hat kein Storno-Recht mehr: Die Route `range-office.cancel-entry` ist entfallen, `Logbook::cancel` gibt 403 zurück. Der Admin storniert und korrigiert nur nach Passwortbestätigung und mit Grund, nie an Zeilen mit `user_id` oder `recorded_by_id` = eigene ID.
- Korrektur:
- In einer Transaktion entstehen eine stornierte alte Zeile und eine neue `late_entry`-Zeile mit `corrects_id`.
- Person und Datum bleiben gleich; Kurz/Lang nur, wenn die Ursprungszeile schon Kurz/Lang war.
- Der Auszug zeigt nur die neue Zeile mit „bestätigt von <Aufsicht der Ursprungszeile>, korrigiert von <Admin>: Grund“; die Kladde des Vereins zeigt beide.
- Bei einer verfallenen Session (`open`, `shot_on` < heute) werden Erfassen, Ablehnen und Abzeichnen abgewiesen. Es entsteht keine Kladde-Zeile, und die Session steht in der Admin-Liste.
- Antrag:
- hat `role=applicant` und `active=false`;
- die Anmeldung wird nur bei korrektem Passwort mit „Antrag liegt beim Vorstand“ abgewiesen;
- nach der Registrierung wird sofort mit dieser Meldung abgemeldet;
- erscheint in keiner Personenliste (Stand, Kiosk, Nachtrag, Einteilung);
- eine Antrags-ID per POST an `stand/…`, an den Nachtrag oder an `Sessions` wird abgewiesen;
- bekommt weder Bescheinigung noch Auszug.
- Für einen Antrag ist das Bearbeiten-Formular gesperrt: Ein PUT auf `admin.users.update` ändert weder `role` noch `active`. Aufnehmen geht nur über die eigene Aktion. Sie setzt `role=member`, `active=true`, `member_since`, `email_verified_at` und `adult_confirmed_at` und schreibt ein Audit.
- Die Selbstregistrierung legt immer `role=applicant` und `active=false` an, auch bei manipuliertem Request. Ist der Schalter aus, gibt es 404. Der Throttle je IP greift. Der Löschlauf entfernt nur Zeilen mit `role=applicant`, die älter als 30 Tage sind, nie deaktivierte Mitglieder.
- Ein Mitglied ohne `adult_confirmed_at` wird nur mit dem Haken „volljährig geprüft“ aktiviert. Der Wert wird gespeichert und protokolliert; danach wird nicht mehr gefragt.
- Bestand: Zeilen mit `source=stand` bleiben gültig. Zeilen mit `recorded_by_id = user_id` (nur aus dem Backfill in …_000900:67 möglich) erscheinen als „aus Buchung übernommen, ohne Bestätigung durch eine Aufsicht“. Die Migration ändert keine Zeile.
- Die Statistik zählt Besuche als unterschiedliche Paare aus Person und Tag, nicht als Zeilen.
- Drucke (a) und (b), als HTML gerendert:
- nur gültige Zeilen;
- Schüsse „–“ bei NULL;
- ein Bestätiger und „erfasst über“ je Zeile;
- in (a) keines der Wörter „erfüllt“, „Bedürfnis“, „glaubhaft“, „regelmäßig“;
- ohne Katalog zeigt (a) „nicht verfügbar“.
- Profil: Ein Mitglied kann seine E-Mail nicht selbst ändern, `email_verified_at` bleibt erhalten. Ändern kann sie nur der Vorstand.

### Drucke

- **(a) Teilnahmenachweis je Disziplin (Kurzformular, Wunsch des Nutzers):** Titel „Teilnahmenachweis aus der Schießkladde“. Inhalt:
- Verein, Verband, Vereinsnummer
- Mitglied mit Nummer
- Disziplin mit Verband:SpO-Nummer und Namen
- Zeitraum, standardmäßig 24 Monate
- je Tag: Datum, Kategorie, eigen/Verein, Schüsse, erfasst über, bestätigt von (Aufsicht; bei Korrektur „bestätigt von …, korrigiert von …: Grund“; bei Nachtrag „Nachtrag: Grund“)
- Anzahl Tage
- Hinweis: „keine Bescheinigung nach § 14 WaffG, keine rechtliche Bewertung; bescheinigen muss der Verband; stornierte und nicht abgezeichnete Einträge fehlen“
- Kopfvermerk „digital seit“
- Feld für Unterschrift und Stempel des Vorstands.
  Entschieden: Er bestätigt die Teilnahme, „erfolgreich absolviert“ steht dort nicht. Das Mitglied bekommt eine Fassung „zur Information“. Ohne gewählten Katalog zeigt die App „nicht verfügbar, solange kein Disziplinkatalog gewählt ist“, statt eines leeren oder unscharfen Drucks. Daten: Neue Vorlage resources/views/pdf/discipline.blade.php.
- Daten: `ShootingRecord::entries` mit Filter `discipline` (gültige Zeilen in `log_entries`), dazu `recordedBy`, `corrects.recordedBy`, `sessionEntry.via` und `ClubSettings`.
- Der Altbestand ohne Disziplin fehlt.
- Kommt erst nach dem Katalog (PR 10 vor PR 8b), weil Freitext ohne Katalog nicht mehr angenommen wird.
- Routen: `record.discipline-pdf` (Mitglied) und `admin.record.member-discipline-pdf`.
- **(b) Übersicht aller Schießtage mit Schüssen (Wunsch des Nutzers):** Erweiterung der vorhandenen Vorlage resources/views/pdf/record.blade.php.
- Spalten: Datum, Kategorie, Waffe, Disziplin, Schüsse („–“, wenn nicht erfasst), erfasst über (Mitglied / Kiosk / Aufsicht / Stand bis zur Umstellung / Nachtrag / Korrektur / aus Buchung übernommen), bestätigt von. Die Kopfzeile heißt „Bestätigt von“ statt „Eingetragen von“ (:26).
- Summen: Tage je Kategorie (alle und nur eigene Waffe) und Schüsse je Kategorie (nur erfasste, mit „davon ohne Angabe: n“).
- Neuer, neutraler Hinweis statt :52-57:
  „Jede Zeile ist von der genannten, im Aufsichtsregister geführten Aufsicht bestätigt (§ 10 Abs. 3 AWaffV): bis <Umstellung> durch Eintragen am Stand, seitdem durch Abzeichnen der vom Mitglied oder von der Aufsicht erfassten Einträge. Nachträge und Korrekturen des Vorstands sind mit Grund gekennzeichnet; aus Buchungen übernommene Zeilen ohne Bestätigung sind markiert. Ein Termin zählt je Tag und Kategorie einmal. Keine Bescheinigung, keine rechtliche Bewertung; bescheinigen muss der Verband.“
- <Umstellung> = erstes `shooting_sessions.signed_at`, berechnet. Gibt es noch keines, entfällt der Satzteil.
- Wie bisher zwei Fassungen: „zur Vorlage beim Verband“ und „zur Information“. Daten:
    - `ShootingRecord::entries` und `ShootingRecord::totals` (gültige Zeilen in `log_entries`), ergänzt um `shots`.
- `sessionEntry.via` für „erfasst über“, `corrects` für Korrekturen, `recorded_by_id = user_id` für „aus Buchung übernommen“.
- `min(shooting_sessions.signed_at)`, `ClubSettings`.
- Zeitplan: Hinweis, Kopfzeile und Markierung „aus Buchung übernommen“ kommen im selben Release wie die Umstellung (PR 4b, R21); Spalten und Summen folgen in PR 8a.
- **(c) Session-Protokoll (vom Nutzer gewünscht; bundesrechtlich keine Pflicht):** - Kopf: Datum, Aufsicht, gestartet um, abgezeichnet um bzw. „verfallen, nicht abgezeichnet“; dazu Stand und Zeitfenster „laut Einteilung“.
- Je Teilnehmer: Mitglied (mit Nummer) oder Gast, aktiviert um, mit oder ohne Buchung.
- Je Eintrag: erfasst um, erfasst über (eigenes Gerät, Kiosk, Aufsicht), Disziplin, Kategorie, eigen/Verein, Schüsse, Status; abgelehnte Einträge mit Grund, zurückgezogene markiert.
- Schluss: „abgezeichnet von … am … um …, bestätigt bis HH:MM“.
- Spätere Stornos und Korrekturen der zugehörigen Kladde-Zeilen.
- Enthält nur die Teilnehmer dieser Session. Daten:
    - `shooting_sessions`, `session_participants`, `session_entries`.
- `log_entries` über `session_entry_id` bzw. `corrects_id`.
- `supervisions` des Tages, zur Druckzeit gelesen und als „laut Einteilung“ beschriftet; kein Snapshot.
- Neue Vorlage resources/views/pdf/session.blade.php, Route nur für den Admin (PR 9). Die Einsicht des Vorstands in abgelehnte Einträge (Rechtsprüfung 5e) hängt nicht daran; die gibt es schon mit PR 7 als Liste.
- **(d) Mitgliedschaftsbescheinigung § 14 Abs. 4 S. 3 WaffG (schon gebaut, vom Nutzer gewünscht):** Die vorhandene Vorlage resources/views/pdf/membership.blade.php, ergänzt um:
- Verband und Vereinsnummer statt des allgemeinen Satzes (:25-26)
- „ist seit <member_since> Mitglied und am Ausstellungstag Mitglied“
- „erstellt am/von“.
  Sie wird nie für Antragsteller ausgestellt; die Vorlage würde für `active=false` sonst „war Mitglied“ drucken (:18-22). Für ausgetretene Mitglieder bleibt diese Fassung. Die Bescheinigung enthält keine Schießtermine, kein Urteil über die 10 Jahre und kein Geburtsdatum (E15). Daten:
    - `users`: name, member_number, member_since, active, role.
- `ClubSettings`: federation, club_number.
- Ausgegeben über `Admin/RecordController::certificate` (app/Http/Controllers/Admin/RecordController.php:140), dort mit Sperre für `role=applicant`.
- **(e) Papier-Ersatzliste (vorhanden, für den Ausfall):** resources/views/pdf/paper-list.blade.php bekommt eine Spalte Schüsse. Statt „Niemand trägt sich selbst ein“ (:38) heißt es: „Mitglieder tragen sich ein, die Aufsicht zeichnet die Liste ab; der Vorstand überträgt sie als Nachtrag“. Der Satz zu Minderjährigen bleibt, bis Q5-Rest entschieden ist. Daten: Leere Vorlage, Kopf aus `ClubSettings`. Der Übertrag läuft über `Logbook::lateEntries` mit optionalem `shots`.

### PR-Schritte

#### PR 0: Doku auf das neue Modell ziehen (S) – mit diesem Dokument

Voraussetzung: –

Agenten bauen gegen die Doku, deshalb kommt sie zuerst.

- docs/UMBAU-SESSION.md: dieses Dokument.
- docs/ENTSCHEIDUNGEN.md: Q1b, Q5, die Komfortfunktionen und E1–E15 als entschieden.
- PRODUCT.md: Erfolgskriterium nach E11 enger gefasst, MVP um Session, Kiosk und Selbstregistrierung ergänzt.
- docs/PLAN.md: Regel 5 und die Abweichung „Mitglieder tragen nichts selbst ein“ gelten als überholt, sobald PR 4b läuft.
- docs/WETTBEWERB-REA.md: Ein Nachtrag sagt, dass 5.2, K1/K2/K6, 7.2 und 8 (Kiosk, Schusszahl, Selbsteintrag, Polling) durch die Entscheidung vom 03.11.2026 überholt sind.

**Tests:** keine; die CI muss grün bleiben.

#### PR 1: Mitgliedsantrag und Aufnahme durch den Verein (M)

Voraussetzung: PR 0: Doku auf das neue Modell ziehen

Die Registrierung bleibt in diesem Schritt aus.

Antrag:

- `Role::Applicant` und `User::isApplicant()`. Ein Antrag ist `role=applicant` und `active=false` (fail-closed).
- Anmeldung: `authenticateUsing` (FortifyServiceProvider.php:48-56) wirft bei Antrag und korrektem Passwort „Ihr Antrag liegt beim Vorstand“.
- `LogoutInactiveUsers` zeigt bei Anträgen dieselbe Meldung statt „Zugang deaktiviert“ (:27).

Admin-Liste:

- Filter „Anträge“ und Zähler (lazy geteilte Prop, nur für Admins).
- „Inaktiv“ schließt Anträge aus (UserController.php:38).

Aktionen:

- Aufnehmen: Dialog „Identität und Volljährigkeit geprüft“; Nummer, „Mitglied seit“ (Default heute), `active=true`, `role=member`, `email_verified_at`, `adult_confirmed_at`; Audit.
- Ablehnen: Zeile löschen, Audit ohne Personendaten.
- Kein „Zuordnen“.

Sperren:

- `edit` und `update` weisen Anträge ab, weil `update` Rolle und `active` aus dem Formular schreibt (UserController.php:113-136, UserRequest.php:25).
- `Logbook::lateEntries` weist Anträge ab (Logbook.php:91 prüft `active` nicht).
- `RecordController::certificate` und `memberPdf` weisen Anträge ab.
- Kein Import-Konflikt mehr nötig: Der CSV-Import wird mit K2 stillgelegt. Kommt K2 nach PR 1, meldet `MemberImport::match` (MemberImport.php:239-252) bis dahin einen Antrag mit gleicher E-Mail oder Nummer als Konflikt.

Weiteres:

- Audit bei `update`: alt → neu für name, member_number, member_since, role, active (statt nur Feldnamen, :125-131).
- Migration `users.adult_confirmed_at` und Haken „volljährig geprüft“ im Anlegen-Formular.
- Profil: E-Mail nur lesbar; ProfileController.php:33-35 würde sonst `email_verified_at` löschen, und ohne Mailversand gibt es keinen Weg zurück.
- Knopf „Link zum Passwort setzen“ auch für Konten, die schon angemeldet waren (Form.svelte:290).

**Tests:** - Factory-State `applicant` (`active=false`).

- Neu:
    - Login abgewiesen mit Antragsmeldung;
    - nicht in den Listen;
    - PUT auf einen Antrag ändert nichts;
    - Aufnehmen und Ablehnen;
    - Nachtrag mit Antrags-ID abgewiesen;
    - Import-Konflikt;
    - keine Bescheinigung;
    - Audit mit Werten;
    - Profil-E-Mail gesperrt.
- Anpassen: UserManagementTest, MemberImportTest, Settings/ProfileUpdateTest:32-43, Auth/AuthenticationTest.

#### PR 2: Selbstregistrierung (M)

Voraussetzung: PR 1: Mitgliedsantrag und Aufnahme durch den Verein

- `Features::registration()` einschalten und `Fortify::registerView` auf auth/Register.svelte zeigen lassen. Felder: Name, E-Mail, Passwort, Pflichthaken „volljährig“ (wird nicht gespeichert), Link zum Datenschutzhinweis.
- `CreateNewUser`:
    - setzt per forceFill `role=applicant` und `active=false` (Fillable deckt `role` nicht ab, User.php:42);
    - bricht ab, wenn `registration_open` = 0 ist;
    - begrenzt per RateLimiter je IP, weil die Fortify-Route keinen Throttle hat (vendor/laravel/fortify/routes/routes.php:81-83, Haupt-Checkout).
- Fortify meldet nach der Registrierung an (RegisteredUserController:64). Die Meldung beim Abmelden kommt aus PR 1 (`LogoutInactiveUsers`).
- Link „Konto anlegen“ in Login bzw. AuthSimpleLayout, nur bei offener Registrierung.
- Schalter `registration_open` (int 0/1) in admin/Settings.svelte, Standard 0.
- Privacy.svelte ergänzen: Antragsdaten, Rechtsgrundlage (Einschätzung Art. 6 Abs. 1 lit. b DSGVO), Löschfrist.
- Command `verein:antraege-loeschen`: nur `role=applicant`, älter als 30 Tage; täglich in routes/console.php.
- DemoSeeder legt einen offenen Antrag an.

**Tests:** - ExampleTest:20 ersetzen: Die Registrierung legt einen Antrag an (`applicant`, inaktiv, danach abgemeldet); bei Schalter aus gibt es 404.

- Rolle und `active` lassen sich nicht manipulieren; Throttle; der Löschlauf trifft nie deaktivierte Mitglieder.
- SettingsTest: neuer Schlüssel.
- E2E: Konto anlegen → Meldung „Antrag liegt beim Vorstand“ → Vorstand nimmt auf → Login → eigene Kladde.
- a11y- und mobile-Prüfung der Registrierungsseite.

#### PR 3: Datenmodell Session und erweiterte Kladde (nur Ergänzungen) (M)

Voraussetzung: PR 0: Doku auf das neue Modell ziehen

Migrationen, jede in einem eigenen `DB::transaction()` und mit Guards (`hasTable`, `hasColumn`, `DROP … IF EXISTS`):

1. create `shooting_sessions` (mit `kiosk_token_hash`), `session_participants`, `session_entries` samt allen Triggern, auch BEFORE INSERT, für SQLite und MySQL.
2. `log_entries`:
    - SQLite: rohes ADD COLUMN `shots`, `session_entry_id`, `corrects_id`; MySQL: Blueprint mit `constrained()`.
    - DROP und CREATE `log_entries_member_unique` mit `coalesce(discipline,'')`.
    - CREATE `log_entries_session_entry_unique`.
    - DROP und CREATE `log_entries_only_cancel`.

Code:

- Models `ShootingSession`, `SessionParticipant`, `SessionEntry` mit Model-Schutz nach dem Muster von LogEntry ('' ↔ null).
- `LogSource::Session`, neue Enums; LogEntry bekommt Casts, Relationen und Docblock.
- `Logbook::findActive` vergleicht zusätzlich `coalesce(discipline,'')`.
- Statistik zählt Paare aus Person und Tag (StatisticsController.php:39-49).

Keine Oberfläche; der alte Ablauf läuft unverändert weiter.

**Tests:** - Generischer Spaltentest; Trigger- und Indexliste in `sqlite_master`.

- INSERT, UPDATE und DELETE auf abgezeichnete Sessions werfen.
- Zwei Disziplinen ergeben zwei Zeilen; Doppelschutz bei leerer Disziplin.
- Ein direkter Insert mit doppelter `session_entry_id` scheitert.
- Die Migration läuft nach simuliertem Teilabbruch erneut durch.
- StatisticsTest; LogbookTest:367 zum generischen Test ausbauen.
- Manuell: die letzte Produktionssicherung lokal migrieren (siehe migration_and_ops).

#### PR 4a: Session-Domäne mit Tests (ohne Oberfläche) (M)

Voraussetzung: PR 3: Datenmodell Session und erweiterte Kladde (nur Ergänzungen), PR 1: Mitgliedsantrag und Aufnahme durch den Verein

Neu app/Domain/Sessions.php. Jede Methode prüft innerhalb von `DB::transaction()` (IMMEDIATE):

- `open`: `canSupervise(heute)`, Datum vom Server.
- `activate(booking)`, `addMember`, `addGuest`:
    - unabhängig vom Buchungsstatus;
    - nicht die Inhaberin, nicht wer heute eine offene Session führt, nur aktive Mitglieder;
    - `adult_confirmed_at` oder Haken; Gast nur mit „volljährig“.
- `removeParticipant`.
- `stage`:
    - jüngste Aktivierung heute, offene Session;
    - der Teilnehmer führt selbst keine offene Session;
    - Katalog, Kategorie, eigen/Verein, Umfang;
    - „heute schon in der Kladde“; Doppel-Tap.
- `withdraw`: nur wer erfasst hat.
- `reject`: Inhaberin, mit Grund.
- `signOff(entryIds)`:
    - Inhaberin, Datum, Register und Umfang;
    - ID-Vergleich mit den aktuellen `pending`-Einträgen;
    - schreibt Kladde-Zeilen über `insertOnce`, danach die Einträge, dann die Session.
- `issueKioskToken`: Inhaberin; speichert den Hash und gibt den Klartext einmal zurück.

In app/Domain/Logbook.php kommt ein Schreibweg für Session-Zeilen dazu: Datum als Parameter statt `today()`.

Noch keine Routen.

**Tests:** Neu tests/Feature/SessionSignOffTest.php. Er deckt die Invarianten 1–7, 9–12, 15 und 23 ab sowie:

- Registerentzug während der Session;
- Liste geändert zwischen Dialog und Abzeichnen;
- Aufsichtswechsel mit schon aktivierter Buchung;
- gegenseitiges Aktivieren zweier Aufsichten;
- verfallene Session von gestern blockiert heute nichts.

#### PR 4b: Umstellung am Stand – Starten, Aktivieren, Erfassen, Abzeichnen (L)

Voraussetzung: PR 4a: Session-Domäne mit Tests (ohne Oberfläche)

Routen unter `stand/session/…`: starten, aktivieren (Buchung, Mitglied, Gast), entfernen, eintragen (`via=stand`), zurückziehen, abzeichnen.

RangeOfficeController:

- `show` liefert die Session, die Teilnehmer und deren Einträge mit „erfasst um“ und „erfasst über“.
- `enter` schreibt in die Session.
- `checkIn` wird zu „Aktivieren“: `Bookings::checkIn` ruft `Logbook::record` nicht mehr auf, der frühe Return blockiert die Teilnahme nicht mehr.
- Die Route `range-office.cancel-entry` entfällt.

Logbook:

- `cancel` nur noch für den Admin.
- `matchingCheckIns` (Logbook.php:174-186) entfällt.

Oberfläche (Show.svelte, EntryForm.svelte):

- Teilnehmerliste statt aller Mitglieder; Suche zum Hinzufügen ohne die eigene Person.
- Disziplin nur mit Katalog, sonst ausgeblendet und NULL.
- Kurz/Lang ausblenden, wenn die Aufsicht nur `exempt` hat.
- Dialog zum Abzeichnen mit „erfasst um“, Teilnehmern ohne Eintrag, Bestätigungstext mit Uhrzeit und angezeigten IDs.
- Hinweis „vor dem Verlassen des Stands abzeichnen“.

Texte im selben Release (R21):

- pdf/record.blade.php: Kopfzeile „Bestätigt von“ (:26), neutraler Hinweis (:52-57), Markierung „aus Buchung übernommen“, Umstellungsdatum berechnet;
- paper-list:38;
- Help.svelte Abschnitte 2 und 3;
- record/Index.svelte;
- admin/record/Index.svelte:121;
- PRODUCT.md Rollen.

DemoSeeder: Altbestand bleibt `stand`, dazu eine abgezeichnete Session.

Ablehnen kommt mit PR 5. Nicht an einem Schießtag deployen.

**Tests:** - LogbookTest umbauen: Der Helfer `enter()` wird zu Session plus Abzeichnen. Die Tests 92, 149, 163, 179, 205, 223, 327, 337 und 350 neu fassen.

- Anpassen: RangeOfficeTest (75, 98, 107), ReviewRegressionTest:79, Admin/AuditTest:31, UserManagementTest:238-244.
- Inhaltstest für den PDF-Hinweis und die Markierung „aus Buchung übernommen“.
- E2E anpassen: day.spec.ts, flows.spec.ts (:18, :63, :128-170), more.spec.ts:12, network.spec.ts:6, mobile.spec.ts:23-45, a11y.spec.ts.

#### PR 5: Erfassen am eigenen Gerät und Ablehnen (M)

Voraussetzung: PR 4b: Umstellung am Stand – Starten, Aktivieren, Erfassen, Abzeichnen

- Neue Seite für aktivierte Teilnehmer: GET `heute`, POST `heute/eintrag`, POST zurückziehen (nur eigene `pending`-Einträge).
- Formular wie EntryForm, vorbelegt aus dem letzten Eintrag. Bei Treffer kommt der Hinweis „schon erfasst (n Schüsse) – zurückziehen und neu?“.
- Liste der eigenen Einträge dieser Session mit Status und Ablehnungsgrund.
- Einziger Einstieg: Karte „Heute schießen“ auf dem Dashboard (DashboardController); kein Navigationspunkt, kein Knopf in Arrived.svelte.
- Ablehnen mit Grund für die Inhaberin in Show.svelte, mit Schnellgründen.

**Tests:** - Feature-Tests:

- nur für sich;
- nur aktiviert, jüngste Aktivierung;
- nur heute, offene Session;
- nicht während einer eigenen offenen Session;
- zurückziehen bis zum Abzeichnen;
- Ablehnung mit Grund sichtbar;
- Antragsteller abgewiesen.
- E2E mobile.spec.ts: Erfassen am Handy, Ablehnen und Abzeichnen durch die Aufsicht.

#### PR 6: Kiosk über Session-Link ohne Anmeldung, mit PIN je Mitglied (M–L)

Voraussetzung: PR 5: Erfassen am eigenen Gerät und Ablehnen

In /stand für die Inhaberin einer offenen Session von heute:

- „Kiosk-Link“: `Sessions::issueKioskToken`, angezeigt als QR wie der Ankunfts-QR.
- „Kiosk auf diesem Gerät“: `Auth::guard('web')->logoutCurrentDevice()`, Sitzung neu, Weiterleitung auf den Link. Bewusst nicht `logout()`, das das Remember-Token wechselt (vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php:670-677, Haupt-Checkout).

Routen `kiosk/{token}` (GET Liste, POST Eintrag):

- Middleware `guest` und `throttle:60,1`; außerhalb von `auth`, also keine geteilten Kontodaten.
- Gültig nur bei Session `open` und `shot_on` = heute, sonst Seite „Session beendet“.
- Keine CSRF-Ausnahme; die Gast-Sitzung trägt das Token.

Kiosk.svelte:

- nur Teilnehmer der Session (Name und Mitgliedsnummer);
- Name antippen → PIN-Feld (Zifferntastatur, maskiert) → EntryForm mit `via=kiosk` und `entered_by_id` = Mitglied → grüne Karte → zurück zur Liste;
- Teilnehmer ohne PIN sind ausgegraut mit dem Hinweis „bitte bei der Aufsicht eintragen“; Gäste stehen nicht in der Liste.

Kiosk-PIN (E2):

- Migration `users.kiosk_pin_hash` (Blueprint-Spalte, `users` hat keine Trigger).
- Das Mitglied setzt bzw. ändert die PIN unter Einstellungen → Sicherheit, 4 bis 6 Ziffern, nach Passwortbestätigung (RequirePassword).
- Der Vorstand kann eine PIN im Mitgliederformular löschen, mit Audit-Eintrag; sehen kann er sie nicht.
- Prüfung mit `Hash::check`. Der RateLimiter-Schlüssel ist Session + Teilnehmer: 5 Versuche, dann 15 min Sperre. Zusätzlich gilt `throttle:60,1` je Gerät.
- kein Zurückziehen, keine Einträge anderer;
- Polling per `usePoll`, Layout ohne Navigation.

Entfällt gegenüber dem Entwurf: KioskLock, Passwort beim Verlassen, Logout-Sperre.

**Tests:**

- Invariante 8 inklusive der PIN-Fälle: falsch, fehlt, Sperre nach 5 Versuchen, kein Klartext.
- PIN setzen nur nach Passwortbestätigung; Löschen durch den Vorstand mit Audit-Eintrag.

- Angemeldete Nutzer werden umgeleitet.
- Nach „Kiosk auf diesem Gerät“ bleibt ein Remember-Cookie auf einem zweiten Gerät gültig.
- Ersetzter Token: 404.
- Unsicher, deshalb prüfen: Nach Standby über 120 min (config/session.php:35) liefert ein POST 419; die Seite muss neu laden statt hängen zu bleiben.

#### PR 7: Änderungen nach dem Abzeichnen nur durch den Admin (M)

Voraussetzung: PR 4b: Umstellung am Stand – Starten, Aktivieren, Erfassen, Abzeichnen

- Neu `Logbook::correct`: in einer Transaktion Storno der alten Zeile und neue Zeile `late_entry` mit `corrects_id` und Grund. Erlaubt nur Kategorie, eigen/Verein, Disziplin und Schüsse; Kurz/Lang nur, wenn die Ursprungszeile Kurz/Lang war.
- `cancel` und `correct` nie an Zeilen mit `user_id` oder `recorded_by_id` = Admin (heute prüft lateEntries nur das Mitglied, Logbook.php:107).
- RequirePassword auf `admin.record.cancel`, `correct` und `late`.
- `lateEntries` und `storeLate` bekommen optional `shots`.
- admin/record/Index.svelte:
    - Formular für die Korrektur;
    - berechnete Liste „verfallene Sessions“ (nur Anzeige, Nachtrag wie bisher);
    - Liste „abgelehnt/zurückgezogen“ mit Grund (Rechtsprüfung 5e);
    - CSV mit Schüssen und Vermerk „Korrektur“.

**Tests:** - Invarianten 16–18.

- Korrektur ohne Passwortbestätigung abgewiesen; Admin an eigener Zeile abgewiesen.
- LogbookTest:187 und :292 um `shots` ergänzen.
- E2E: flows.spec.ts:97 mit Nachtrag samt Schüssen und mit Passwortbestätigung.

#### PR 8a: Mitgliedersicht und Drucke mit Schüssen (M)

Voraussetzung: PR 4b: Umstellung am Stand – Starten, Aktivieren, Erfassen, Abzeichnen

- `ShootingRecordController::entry` liefert zusätzlich:
    - `shots`;
    - „erfasst über“;
    - „bestätigt von“, bei Korrektur mit Ursprungs-Aufsicht und korrigierendem Admin.
- `ShootingRecord::totals` summiert Schüsse je Kategorie; NULL wird „–“, mit Zähler „ohne Angabe“.
- Spalten in record/Index.svelte und admin/record/Index.svelte.
- pdf/record.blade.php: Spalten und Summen.
- membership.blade.php: Verband, Vereinsnummer, „am Ausstellungstag Mitglied“, „erstellt von“.
- paper-list: Spalte Schüsse.
- bookings/Index.svelte: Status-Label „aktiviert“.

**Tests:** - Inhaltstests über `view('pdf.…')->render()` (Invariante 26).

- LogbookTest:278, :308 und :317 anpassen.
- E2E: more.spec.ts:125-143.

#### PR 10: Disziplinkatalog (O1 beantwortet: BDS, Katalog eingetragen) (S)

Voraussetzung: PR 0: Doku auf das neue Modell ziehen

- config/disziplinen.php für den Verband des Pilotvereins nach dessen Sportordnung befüllen. Nummern vom Verband übernehmen, nicht raten.
- `discipline_catalog` in `ClubSettings::KEYS` aufnehmen, mit Auswahl in den Einstellungen. Inhalte kommen weiter per Release.
- Regel im Dateikopf: Schlüssel werden nie gelöscht oder umbenannt.

**Tests:** - SettingsTest: neuer Schlüssel.

- Disziplin ist Pflicht, sobald ein Katalog gewählt ist; ohne Katalog wird sie als NULL gespeichert.
- E2E: Helfer `pickDisciplineIfAsked`.

#### PR 8b: Teilnahmenachweis je Disziplin (a) (S)

Voraussetzung: PR 8a: Mitgliedersicht und Drucke mit Schüssen, PR 10: Disziplinkatalog (O1 beantwortet)

- Neue Vorlage pdf/discipline.blade.php mit Wortlaut „Teilnahmenachweis aus der Schießkladde“ (entschieden: Teilnahme, kein Urteil).
- Routen für das Mitglied („zur Information“) und den Admin.
- Knopf je Disziplin in record/Index.svelte und admin/users/Form.svelte. Ohne Katalog heißt es „nicht verfügbar“.

**Tests:** Inhaltstest:

- Disziplinfilter;
- nur gültige Zeilen;
- keine bewertenden Wörter;
- Bestätiger und „erfasst über“ je Zeile;
- Korrektur mit Ursprungs-Aufsicht;
- ohne Katalog „nicht verfügbar“.

#### PR 9: Session-Protokoll (c) (S)

Voraussetzung: PR 7: Änderungen nach dem Abzeichnen nur durch den Admin, PR 8a: Mitgliedersicht und Drucke mit Schüssen

- Neue Vorlage pdf/session.blade.php.
- Admin-Route und Knopf in der Session-Liste der Kladde des Vereins.
- Daten aus den Session-Tabellen und aus `log_entries` (Storno, Korrektur).

**Tests:** Inhaltstest:

- abgelehnte Einträge mit Grund;
- „abgezeichnet von … bis HH:MM“ bzw. „verfallen“;
- spätere Korrektur;
- nur Teilnehmer dieser Session.

#### PR K1: Gruppen und Serien-Verknüpfung stilllegen (M)

Voraussetzung: – (unabhängig von den Session-Schritten)

**Vorher:** Auf einer Sicherung prüfen, ob Tage mit `restricted_to_groups = 1` existieren. Solche Tage werden mit dem Stilllegen für alle sichtbar, auch die, deren Gruppe schon gelöscht ist.

**Gruppen:**

- Die vier `admin.groups`-Routen fallen weg, ebenso `GroupController`, `pages/admin/groups/Index.svelte` und der Sidebar-Eintrag.
- Aus Mitgliedern (`Admin/UserController`, `UserRequest`, `users/Form.svelte`, `users/Index.svelte`) und Schießtagen (`Admin/ShootingDayController`, `ShootingDayRequest`, `shooting-days/Form.svelte`, `Index.svelte`) fallen die Gruppenfelder weg.
- Aus Dashboard, Terminliste, `DayCard`, `Show.svelte` und `Bookings::ensureDayIsBookable`/`assignSupervision` fallen `visibleTo`/`isOpenFor` weg.
- Der Schalter „Gastschützen erlaubt“ bleibt und bekommt die Beschriftung „nur für die Online-Buchung“.

**Serien:**

- `store` schreibt keine UUID mehr. Der Folgetermin-Zweig in `update`, `seriesCount`, die Checkbox „auch für Folgetermine“ und das Serien-Symbol fallen weg.
- Die wöchentliche Mehrfachanlage (`repeat_until`, `dates()`) bleibt.

Tabellen und Spalten bleiben stehen. Das Label `group.*` in `Audit.svelte` bleibt wegen alter Protokolleinträge.

**Tests:**

- Die Gruppen-Tests in `UserManagementTest`, `ShootingDayManagementTest`, `BookingRulesTest`, `BookingHttpTest` und `ReviewRegressionTest` fallen weg, ebenso der Gruppenaufruf in `AuditTest` und `/verwaltung/gruppen` in `tests/e2e/mobile.spec.ts`.
- Die Serien-Tests (`ShootingDayManagementTest` Z. 101–124, `ReviewRegressionTest` Z. 125–149) fallen weg. Die UUID-Prüfung in `test_admin_creates_a_weekly_series` wird gestrichen.
- PHPStan und der Wayfinder-Build finden übersehene Verweise.

#### PR K2: CSV-Import, Erinnerung, Kalender-Abo und „Aufsicht gesucht“ stilllegen (S)

Voraussetzung: –

- **CSV-Import:** Die Routen fallen weg, ebenso `MemberImportController`, `app/Domain/MemberImport.php`, `users/Import.svelte` und der Knopf „Importieren“. Das Label `user.imported` bleibt.
- **Erinnerung am Vortag:** `routes/console.php:6`, `SendReminders` und `TomorrowReminder` fallen weg.
- **Kalender-Abo:**
    - `calendar.feed` und `calendar.regenerate` fallen weg, ebenso `CalendarController`, `calendarUrl` und der Abschnitt in `bookings/Index.svelte`.
    - Die Spalte `calendar_token` bleibt. Eine Datenmigration leert sie, damit alte Links tot bleiben.
    - Die Mitglieder vorher in der App informieren: Abonnierte Kalender liefern danach 404.
- **„Aufsicht gesucht“:**
    - `routes/console.php:7`, `RequestSupervision` und `SupervisionNeeded` fallen weg.
    - Der Hinweis „Hier fehlt noch Standaufsicht“ auf der Übersicht bleibt.
- **Absage-Mail:** Sie bleibt. Der Text der Meldung lautet nur dann „benachrichtigt“, wenn ein echter Mailer läuft.
- **Texte:** README, ENTSCHEIDUNGEN.md und der Warntext in den Einstellungen („Einladungen, Absagen und Erinnerungen“) werden angepasst.
- **Tests:**
    - `MemberImportTest`, `RemindersAndCalendarTest` und `SupervisionRequestTest` fallen weg.
    - Der Import-Test in `ReviewRegressionTest` fällt weg.
    - Die Import-Abläufe in `tests/e2e/more.spec.ts` und `mobile.spec.ts` fallen weg.

#### PR K3: Passkeys und ungenutzte Reste des Starter-Kits entfernen (S)

Voraussetzung: SMTP ist eingerichtet und „Passwort vergessen“ funktioniert. Sonst ist ausgesperrt, wer sich nur per Passkey anmeldet und sein Passwort nicht kennt.

- **Passkeys:**
    - Der Schalter in `config/fortify.php` fällt weg, ebenso die Routen in `routes/settings.php` und die Komponenten `ManagePasskeys`, `PasskeyItem`, `PasskeyRegister` und `PasskeyVerify`.
    - Die Anzeige in `Login.svelte` (Z. 40, ohne Bedingung), `ConfirmPassword.svelte` und `settings/Security.svelte` fällt weg.
    - Das npm-Paket `@laravel/passkeys` fällt weg. Die Tabelle bleibt.
- **Reste:** `AppHeaderLayout`, `AppHeader`, `AuthCardLayout`, `AuthSplitLayout`, `PlaceholderPattern` und die UI-Komponenten `collapsible`, `select`, `skeleton`, `navigation-menu` und `sheet` werden gelöscht, sofern sie weiter nirgends importiert sind.
- **Nicht löschen:** `app/Actions/Fortify/CreateNewUser.php` und `Fortify::createUsersUsing`. Beide braucht die Selbstregistrierung (PR 2).
- **Tests:** Passkey-Tests unter `tests/Feature/Auth` und `Settings/SecurityTest`.

### Bleibt unverändert

- Planungsebene mit allen Regeln (Q2 = A): Schießtage, Stände, Aufsichtsschichten, Buchung mit Kapazität, Raster und „ohne Aufsicht keine Buchung“. Betrifft `Bookings::book`, `assignSupervision`, `removeSupervision`, `Schedule` und `TimeWindow`.
- „Nicht erschienen“ und „zurücknehmen“ samt `ensureLaneIsFreeAgain` (app/Domain/Bookings.php:170-238).
- Aufsichtsregister `qualifications` (nur anhängen, Trigger) und `User::canSupervise`. Es ist die Zusatzberechtigung aus Punkt 2. Die Regel „Register nie für sich selbst“ bleibt (UserController.php:161).
- `active` als Sperre für Login, Sitzung und Personenlisten (FortifyServiceProvider.php:51, LogoutInactiveUsers.php:22). Sie deckt jetzt auch Anträge ab.
- `log_entries` bleibt die einzige Kladde, insert-only, mit `active_key` und dem Trigger-Muster aus …_001100. Sie wird erweitert, nicht ersetzt (Q7 = B). Der Model-Schutz per Deny-Diff (app/Models/LogEntry.php:56-66) deckt neue Spalten automatisch ab.
- `Logbook::insertOnce`, `ensureGate` (jetzt am Starten und Abzeichnen; die Selbst-Klausel ist strukturell), `ensureDiscipline` und `lateEntries` (K9) werden weiterverwendet.
- Altbestand mit `source=stand` gilt weiter, ohne Backfill. `recorded_by_id` bedeutet unverändert „bestätigt von“; nur die Darstellung markiert Backfill-Zeilen ohne Aufsicht.
- Q3 = C: neutrale Zählung der Tage je Kategorie (alle bzw. nur eigene Waffe), kein Urteil „erfüllt“.
- Einladungslink (app/Support/Invitation.php, InvitationController.php:39-42 setzt `email_verified_at`), Mitglieder ohne Konto (K4). Der CSV-Import wird stillgelegt (K2).
- QR-Ankunft (ArrivalCode, `arrived_at`) als Hinweis „ist da“ für die Aufsicht.
- dompdf, pdf/layout.blade.php, Papier-Ersatzliste, Mitgliedschaftsbescheinigung, ClubSettings und Impressum (#51).
- Protokoll (`audit_entries`), Statistik (geändert wird nur die Zählweise), Absage-Mail, Zwei-Faktor-Anmeldung, Schalter „Gastschützen erlaubt“. Gruppen, Serien-Verknüpfung, CSV-Import, Erinnerungen, Kalender-Abo, „Aufsicht gesucht“ und Passkeys werden stillgelegt (K1 bis K3).
- Betrieb: nächtliche Sicherung, Deploy nur nach grüner CI mit Pflicht-Sicherung, Wartungsseite, cron.php.
- Polling der Standansicht alle 30 s (Show.svelte:91). Mit mehreren schreibenden Geräten ist es jetzt begründet.
- Bis zur Entscheidung zu Q5-Rest bleiben Minderjährige, Vorkommnisse und Aushang wie heute auf Papier. Das ist der Stand, keine Entscheidung. Außerdem: keine Offline-Warteschlange (Q4 = B), SQLite (Q8 = A).

### Bewusst nicht gebaut

- Ausweis- oder Kartenleser am Kiosk. Die PIN je Mitglied genügt (E2).
- Kiosk auf einem angemeldeten Konto mit KioskLock-Middleware, Passwort zum Verlassen und Logout-Sperre. Ersetzt durch einen Session-Link ohne Anmeldung (Blocker 1).
- Bearbeiten offener Einträge. Zurückziehen und neu erfassen genügt, und es gibt kein Zeitfenster zwischen Sichtprüfung und Abzeichnen (Blocker 2).
- „Zuordnen“ eines Antrags zu einem bestehenden Datensatz. Ohne Mailversand wäre das eine mögliche Kontoübernahme; der vorhandene Einladungslink nach persönlicher Identitätsprüfung deckt den Fall ab.
- Status `closed` und „Session schließen“ durch den Admin. „Verfallen“ wird berechnet; was zählen soll, dokumentiert der Grund des Nachtrags. Damit entfallen auch ewig offene Einträge in einer geschlossenen Session (E6).
- Ortsprüfung oder Geofencing gegen Erfassen von zu Hause. Stattdessen „erfasst um“ im Dialog, Uhrzeit im Bestätigungstext und der Hinweis „vor dem Verlassen abzeichnen“. Das Rest-Risiko ist dokumentiert.
- QR-Code je Session zum Selbstbeitritt und Anfrage „bin da“ ohne Buchung. Aktivieren muss die Aufsicht vor Ort (Rechtsprüfung 1d). Der Ankunfts-QR bleibt nur Hinweis.
- Fremdschlüssel von der Session auf Schicht oder Schießtag. Die Session hängt an Aufsicht und Datum, nicht an Zeilen, die hart gelöscht werden können.
- Übergabe einer Session an eine andere Aufsicht und mehrere Aufsichten in einer Session. Jede Aufsicht führt ihre eigene; beim Wechsel wird abgezeichnet und neu aktiviert.
- Summieren der Schüsse einer zweiten Runde gleicher Disziplin am selben Tag in die Kladde-Zeile. Der Trigger verbietet das Update; die Schüsse stehen in `session_entries`.
- Prüfung der Kategorie gegen die `weapon_types` des Stands beim Erfassen. Rechtlich nötig ist nur der Umfang der Aufsicht.
- Urteil „erfüllt“ oder „erfolgreich“, Mindestschusszahl, Ringzahl, Ergebnis, Kaliber.
- Minderjährige im System, also Geburtsdatum, Einverständnis der Sorgeberechtigten und Jugend-Eignung. Gespeichert wird nur „volljährig geprüft“ (E11).
- Warteliste. „Vorgemerkt“ wird als „gebucht“ gelesen.
- Pflege der Disziplinen durch den Admin. Die Inhalte kommen per Release; in den Einstellungen wird höchstens der Katalog ausgewählt.
- MariaDB im Detail: funktionaler Index bzw. virtuelle Spalten und MariaDB in der CI. Der MySQL/MariaDB-Zweig bekommt nur den schwächeren Index und bleibt ungetestet (Q8 = A).
- Eigene Antragstabelle, Konto ohne Mitgliedschaft und Warteseite für angemeldete Antragsteller. Ein Antrag kann sich nicht anmelden.
- Honeypot, Captcha, Beitrittscode. Throttle, Freigabe durch den Vorstand und der Löschlauf genügen.
- Bestätigungsmail bei der Registrierung und die Mail „Antrag angenommen“, solange `MAIL_MAILER=log` gilt. Stattdessen gibt es einen Zähler für den Admin.
- Eigener Throttle auf `password.confirm.store`, den die Fortify-Route nicht hat (routes.php:128-130). Ein Angreifer bräuchte schon eine Admin-Sitzung, und der Kiosk braucht kein Passwort, nur die PIN des Mitglieds.
- Sperre oder Protokoll für Namensänderungen im Profil. Gesperrt wird nur die E-Mail.
- Mehrere Einstiege zu „Heute schießen“. Es gibt nur die Dashboard-Karte, keinen Navigationspunkt und keinen Knopf in Arrived.svelte.
- Spalten `activated_by_id` und `opened_at`; sie wären redundant zu supervisor_id und created_at.
- Entfernen von `Role::Guest`. Das ist ein Altlast-Thema ohne Bezug zum Modell.
- Automatisches „nicht erschienen“ beim Abzeichnen.
- Schusszahl je Buchung in „Meine Buchungen“. Die Schüsse stehen in der Kladde.
- Sammeldruck für alle Mitglieder in einem dompdf-Lauf. Ob Speicher und Laufzeit auf dem Webhosting reichen, ist ungeprüft.
- Trigger für `audit_entries`. Die Dokumentation der Kladde liegt unveränderlich in `log_entries` (Storno-Felder, `corrects_id`).
- Geburtsdatum auf der Bescheinigung (Datensparsamkeit). Erst bauen, wenn die Behörde es verlangt.
- Backfill von Altzeilen. Der Trigger verbietet ihn, und er ist auch nicht nötig.
- Websockets und eine Offline-Warteschlange.

### Migration und Betrieb

- Basis:
- Die PRs setzen auf origin/main 352eb7f auf. Zeilenangaben beziehen sich auf 6505254, außer wo „origin/main“ steht.
- #51/#52 haben geändert: routes/web.php (Gruppe `auth`/`verified` jetzt bei :47), HandleInertiaRequests.php (`auth.user` bei :45), AppSidebar.svelte, admin/Settings.svelte, ClubSettings.php und AuthSimpleLayout.svelte. Das betrifft PR 1, 2, 5 und 6.
- Laravel ist 13.39.0 (composer.lock; Haupt-Checkout vendor/…/Foundation/Application.php:49), nicht 12.
- Geprüft im vendor/ des Haupt-Checkouts; im Checkout rea-doc gibt es kein vendor/: Laravel führt SQLite-Migrationen ohne Transaktion aus. Schema/Grammars/Grammar.php:31 setzt `$transactions = false`, nur Postgres und SqlServer setzen true; ausgewertet in Migrator.php:449. Jede neue Migration fasst sich deshalb selbst in `DB::transaction()`, auch die mit den Session-Tabellen. Auf SQLite ist DDL transaktional. Unter MariaDB committet DDL implizit; das Fenster bleibt, ungetestet.
- Geprüft (Haupt-Checkout vendor/): Auf SQLite lösen `change`, `primary`, `dropPrimary`, `foreign` und `dropForeign` einen Neubau der Tabelle aus (SQLiteGrammar.php:36, compileAlter :324-370: CREATE temp, Kopie, DROP, RENAME). Dabei verschwinden die Trigger ohne Fehlermeldung.
- An `log_entries`, `bookings` und `qualifications` deshalb nur rohes `ALTER TABLE … ADD COLUMN … REFERENCES …`; das ist erlaubt, weil der Default NULL ist.
- MySQL-Zweig: Blueprint mit `constrained()`, weil MySQL beim ALTER die Trigger behält. Unsicher: MySQL vor Version 9 ignoriert inline `REFERENCES` bei ADD COLUMN.
- `users` hat keine Trigger; `adult_confirmed_at` kommt als Blueprint-Spalte ohne `constrained()`.
- Wiederholung ohne SSH: cron.php ruft jede Minute `migrate --force` auf (cron.php:57). Bricht eine Migration ab, wird die Wartungsseite nicht entfernt (:59-61). Unsicher ist, ob `Kernel::call` die Ausnahme wirft oder nur ausgibt; das im Probelauf prüfen. Deshalb hat jede Migration Guards: `Schema::hasTable`/`hasColumn`, `DROP TRIGGER IF EXISTS`, `DROP INDEX IF EXISTS` vor jedem CREATE. So läuft ein zweiter Lauf auch dann durch, wenn der Prozess zwischen COMMIT und dem Eintrag in `migrations` endet.
- SQLite-Version in Produktion: aus dem Kopf der letzten Sicherung lesen (Offset 96, 4 Byte big-endian, `xxd -s 96 -l 4 <sicherung>`). VACUUM INTO schreibt dort die Version der Bibliothek auf dem Hetzner-Server. Generierte STORED-Spalten (ab 3.31) laufen schon (`active_key`, …_000900:36), Ausdrucksindizes gibt es ab 3.9. Unsicher, deshalb im Probelauf prüfen: ADD COLUMN an einer Tabelle mit STORED-Spalte.
- Reihenfolge in PR 3:

1. die drei Session-Tabellen mit allen Triggern, in einer Transaktion;
2. `log_entries` in einer Transaktion: ADD COLUMN, DROP/CREATE `log_entries_member_unique`, CREATE `log_entries_session_entry_unique`, DROP/CREATE `log_entries_only_cancel`.
   Der neue Member-Index hat nur eine Spalte mehr; Bestandszeilen können ihn nicht verletzen. `down()` setzt nur Indizes und Trigger zurück, die Spalten bleiben.

- Kein Backfill: Der Trigger blockt Updates, deshalb sind alle neuen Spalten nullable. Altzeilen zeigen Schüsse „–“ und die Herkunft „Stand“. Sessions werden nachträglich nicht erfunden.
- Vor dem Deploy von PR 3 (lokal, ohne SSH):
- die letzte Produktionssicherung aus ~/bambamdoku-backups kopieren und mit `php artisan migrate` migrieren;
- `pragma integrity_check` laufen lassen;
- Trigger- und Indexliste in `sqlite_master` prüfen;
- die Version aus dem Dateikopf prüfen.
  Die Pflicht-Sicherung im Deploy-Skript bleibt der Rückweg.
- Vor PR 4b in derselben Sicherung zählen: `select count(*) from log_entries where recorded_by_id = user_id`. Solche Zeilen kann nur der Backfill (…_000900:67, `checked_in_by_id ?? booked_by_id`) erzeugt haben. Die Markierung „aus Buchung übernommen“ kommt unabhängig vom Ergebnis; die Zahl zeigt nur, wie viele Auszüge betroffen sind.
- cron.php spielt die Migration spätestens eine Minute nach dem Deploy ein. PR 3 ergänzt nur; der alte Code kommt mit den nullable Spalten und dem lockereren Index zurecht.
- PR 4b nicht an einem Schießtag deployen. Danach erzeugt ein Check-in keinen Kladde-Eintrag mehr. Buchungen mit `checked_in` aus der Zeit davor bleiben und erscheinen als „aktiviert“. Allgemein gilt ab PR 4b: kein Deploy am Abend eines Schießtags, weil ein Abzeichnen nach 23:59 abgewiesen wird.
- Während der Wartungsseite schlagen Requests von Handys und Kiosk fehl. Erfassen und Abzeichnen sind idempotent (Unique-Indizes, `insertOnce`, ID-Vergleich), Neusenden legt also nichts doppelt an.
- Der Löschlauf für Anträge läuft täglich über den Schedule (routes/console.php) und damit über cron.php bzw. `schedule:run`. Er filtert auf `role=applicant`, nie nur auf `active=false`.
- PR 2 erst, wenn PR 1 in Produktion läuft. Der Schalter `registration_open` bleibt 0, bis der Vorstand ihn einschaltet. Ist später SMTP eingerichtet, müssen Throttle und Freigabe schon stehen.
- PR 6 (Kiosk) muss vor dem ersten Tablet stehen, das Mitglieder bedienen. Bis dahin erfasst die Aufsicht selbst (`via=stand`).
- Ohne Registerzeile gibt es keine Session. Laut docs/ENTSCHEIDUNGEN.md steht noch niemand im Register; der Vorstand muss das vor dem ersten Schießtag nachholen. Für die eigene Registerzeile (UserController.php:161) und für Korrekturen an eigenen Zeilen braucht der Verein mindestens zwei Admins.
- Den Rechtsstand vor dem Release erneut prüfen (O12): WaffG Stand 22.10.2026, AWaffV Stand 11.07.2025, WaffVwV von 2012.
