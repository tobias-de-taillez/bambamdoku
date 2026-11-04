# Übergabe (Stand 03.11.2026)

## Zuerst tun

1. **Aufsicht registrieren.** In Produktion steht noch niemand im Aufsichtsregister. Deshalb ist „Session starten“ am Stand für alle gesperrt. Das ist kein Fehler, sondern das Eintrags-Tor (§ 10 Abs. 3 AWaffV). Sich selbst kann niemand registrieren. Tobias trägt also Magnus ein (Mitglieder → Magnus → „Prüfung eintragen“) oder Magnus Tobias.
2. **Passwort von Magnus ändern.** „123“ war ausdrücklich so gewünscht, ist auf einer öffentlich erreichbaren Seite aber unsicher. Der Login ist auf 5 Versuche je Minute begrenzt, das hält Raten nur auf.
3. **Vereinsdaten pflegen.** Unter Einstellungen: Name, Anschrift, Verband, Vereinsnummer, „Kladde digital seit“. Diese Angaben stehen im Kopf der Ausdrucke.
4. **Vor dem nächsten Deploy:** SMTP-Zugangsdaten in die `.env` (siehe [DEPLOY.md](DEPLOY.md)). Ohne Mailversand gibt es kein „Passwort vergessen“, und seit #57 auch keine Passkeys mehr. Danach die Registrierung in den Einstellungen freischalten, wenn Anträge kommen sollen.
5. **Disziplinkatalog:** Der Katalog des BDS (Kennziffernliste 07/2026, 291 Disziplinen) steht in `config/disziplinen.php` (O1). Der Vorstand wählt ihn in den Einstellungen; danach ist die Disziplin bei Kurz- und Langwaffe Pflicht. Neue Fassungen der Liste per Pull Request nachtragen, Schlüssel nie löschen.
6. **Kiosk-PIN:** Mitglieder, die am Tablet eintragen sollen, legen unter Einstellungen → Sicherheit eine PIN fest.

## Betrieb

- **Mail** ist nicht eingerichtet (`MAIL_MAILER=log`). Einladungen gibt man als Link weiter, der Link steht nach dem Anlegen in der Mitgliederliste.
- **Sicherung:** Jede Nacht um 03:30 auf dem Server, 30 Tage lang. Ein verpasster Lauf wird nachgeholt. In Produktion geprüft am 03.11.2026. Zusätzlich sichert jeder Deploy vorher nach `~/bambamdoku-backups/`.
- **Deploy** (`scripts/deploy-hetzner.sh`) läuft nur bei grüner CI auf `main`. Die Seite zeigt dabei etwa 2 Minuten „Kurze Wartung“. Bleibt sie hängen, `storage/logs/laravel.log` per FTP lesen. Details: [DEPLOY.md](DEPLOY.md).

## Was in der Nacht vom 02. auf den 03.11. dazukam

| PR       | Inhalt                                                                                                                                                                                        |
| -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| #37, #38 | Browser-Tests der Kernabläufe in der CI. Behoben: Knopf „Auszug …“ fehlte nach dem Mitgliederfilter; vergangene Zeiten waren heute noch anklickbar.                                           |
| #39      | Deploy sichert vorher die Datenbank und zeigt während des Uploads eine Wartungsseite.                                                                                                         |
| #40      | Browser-Tests: Gast, Registerentzug, Einladung, Storno, eigene Kladde, Deaktivieren.                                                                                                          |
| #41      | CSV-Import auch für Mitglieder ohne E-Mail (Zuordnung über die Mitgliedsnummer). Browser-Tests: QR-Ankunft, nicht erschienen.                                                                 |
| #42      | Barrierefreiheit: Kontrast nach WCAG AA, axe-Prüfung hell und dunkel.                                                                                                                         |
| #43      | Schlechtes Netz am Stand: klare Meldung statt stummem Fehlschlag; abgelaufene Sitzung lädt neu.                                                                                               |
| #45      | Verband, Vereinsnummer und „digital seit“ in den Einstellungen (vorher nur über die `.env`).                                                                                                  |
| #46, #47 | Verpasste Nachtsicherung wird nachgeholt. Befunde einer unabhängigen Durchsicht: Migration läuft vor dem Ende der Wartung, Sicherung der ruhenden DB, Import hält die Zuordnung der Vorschau. |

Insgesamt prüfen jetzt über 200 PHP-Tests und 23 Browser-Abläufe jeden PR.

## Offen, entscheidest du

- Q6: Aufbewahrungsfristen

Fragen und Empfehlungen stehen in [ENTSCHEIDUNGEN.md](ENTSCHEIDUNGEN.md), der Bauplan in [UMBAU-SESSION.md](UMBAU-SESSION.md).

Entschieden am 03.11.2026:

- Q2 = A: Die Buchung bleibt, hat aber keinen Nachweiswert.
- Q1b: Session-Modell mit Selbsteintrag, Abzeichnen und Kiosk.
- Q5: die Drucke (a) bis (d).
- die Komfortfunktionen.
- die Detailfragen E1–E15 zum Session-Modell: alle wie empfohlen, nur der Kiosk bekommt eine PIN je Mitglied.

Gebaut am 03.11.2026 in den PRs #54 bis #69, ohne Deploy. Was beim Bau selbst entschieden wurde, steht zum Review in [UMBAU-NOTIZEN.md](UMBAU-NOTIZEN.md).

Offen für den Feldtest (M6): ein echter Schießtag ohne Papierliste mit dem Verein.
