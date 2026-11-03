/**
 * Was der Rollen-Rundgang belegen muss: je Zeile ein Zusammenspiel zwischen Rollen
 * („A tut X → B sieht Y“) oder eine Grenze. Die Geschichten verweisen mit `belegt`
 * auf die Kennungen, der Bericht zeigt je Zeile die Fotos oder „fehlt“.
 *
 * `e2e` nennt die Spec unter tests/e2e, die den Ablauf schon ohne Fotos prüft.
 */
export type Rolle =
    | 'Gast'
    | 'Antragsteller'
    | 'Mitglied'
    | 'Jugendlicher'
    | 'Aufsicht'
    | 'Vorstand'
    | 'Kiosk'
    | 'Gastschütze';

export type Zeile = {
    id: string;
    bereich: string;
    ablauf: string;
    rollen: Rolle[];
    e2e?: string;
};

export const MATRIX: Zeile[] = [
    // Aufnahme
    {
        id: 'A1',
        bereich: 'Aufnahme',
        ablauf: 'Gast öffnet Startseite, Anmeldung und Datenschutzhinweis ohne Konto',
        rollen: ['Gast'],
        e2e: 'a11y.spec.ts',
    },
    {
        id: 'A2',
        bereich: 'Aufnahme',
        ablauf: 'Vorstand schaltet den Online-Antrag frei → Gast sieht „Mitgliedschaft beantragen“',
        rollen: ['Vorstand', 'Gast'],
        e2e: 'application.spec.ts',
    },
    {
        id: 'A3',
        bereich: 'Aufnahme',
        ablauf: 'Gast stellt den Antrag (nur volljährig) → als Antragsteller noch keine Anmeldung',
        rollen: ['Gast', 'Antragsteller'],
        e2e: 'application.spec.ts',
    },
    {
        id: 'A4',
        bereich: 'Aufnahme',
        ablauf: 'Vorstand sieht den Antrag und nimmt auf → neues Mitglied meldet sich an',
        rollen: ['Vorstand', 'Antragsteller', 'Mitglied'],
        e2e: 'application.spec.ts',
    },
    {
        id: 'A5',
        bereich: 'Aufnahme',
        ablauf: 'Vorstand lehnt einen Antrag ab → Anmeldung bleibt gesperrt',
        rollen: ['Vorstand', 'Antragsteller'],
    },
    {
        id: 'A6',
        bereich: 'Aufnahme',
        ablauf: 'Vorstand legt ein Mitglied an und lädt ein → Eingeladene setzt Passwort, Link gilt nur einmal',
        rollen: ['Vorstand', 'Mitglied'],
        e2e: 'more.spec.ts',
    },
    {
        id: 'A7',
        bereich: 'Aufnahme',
        ablauf: 'Mitglied setzt die eigene Kiosk-PIN nach Passwortbestätigung',
        rollen: ['Mitglied'],
        e2e: 'session-device.spec.ts',
    },

    // Planung und Buchung
    {
        id: 'P1',
        bereich: 'Planung und Buchung',
        ablauf: 'Vorstand legt einen Schießtag an → Mitglied sieht ihn unter „Schießtage“',
        rollen: ['Vorstand', 'Mitglied'],
        e2e: 'day.spec.ts',
    },
    {
        id: 'P2',
        bereich: 'Planung und Buchung',
        ablauf: 'Aufsicht trägt sich für einen Termin ein → Vorstand und Mitglieder sehen die Aufsicht',
        rollen: ['Aufsicht', 'Vorstand', 'Mitglied'],
        e2e: 'day.spec.ts',
    },
    {
        id: 'P3',
        bereich: 'Planung und Buchung',
        ablauf: 'Mitglied bucht eine Bahn → Vorstand sieht mehr Buchungen, Aufsicht sieht die Buchung am Stand',
        rollen: ['Mitglied', 'Vorstand', 'Aufsicht'],
        e2e: 'flows.spec.ts',
    },
    {
        id: 'P4',
        bereich: 'Planung und Buchung',
        ablauf: 'Mitglied storniert die eigene Buchung → die Bahn ist wieder frei',
        rollen: ['Mitglied', 'Vorstand'],
        e2e: 'more.spec.ts',
    },
    {
        id: 'P5',
        bereich: 'Planung und Buchung',
        ablauf: 'Vorstand sagt einen Schießtag ab → Mitglied sieht die Absage; Vorstand öffnet ihn wieder',
        rollen: ['Vorstand', 'Mitglied'],
    },
    {
        id: 'P6',
        bereich: 'Planung und Buchung',
        ablauf: 'Vorstand pflegt die Stände → neue Stände stehen bei der Planung zur Wahl',
        rollen: ['Vorstand'],
    },

    // Am Stand
    {
        id: 'S1',
        bereich: 'Am Stand',
        ablauf: 'Aufsicht startet die Session am Stand',
        rollen: ['Aufsicht'],
        e2e: 'flows.spec.ts',
    },
    {
        id: 'S2',
        bereich: 'Am Stand',
        ablauf: 'Aufsicht vermerkt gebuchte Mitglieder als anwesend oder nicht erschienen',
        rollen: ['Aufsicht', 'Mitglied'],
        e2e: 'day.spec.ts',
    },
    {
        id: 'S3',
        bereich: 'Am Stand',
        ablauf: 'Aufsicht aktiviert ein Mitglied → Mitglied sieht „Heute schießen“ am eigenen Handy',
        rollen: ['Aufsicht', 'Mitglied'],
        e2e: 'session-device.spec.ts',
    },
    {
        id: 'S4',
        bereich: 'Am Stand',
        ablauf: 'Mitglied trägt am eigenen Handy ein → Aufsicht sieht den Eintrag in der Session',
        rollen: ['Mitglied', 'Aufsicht'],
        e2e: 'session-device.spec.ts',
    },
    {
        id: 'S5',
        bereich: 'Am Stand',
        ablauf: 'Aufsicht gibt den Kiosk frei → Tablet zeigt ohne Anmeldung nur die aktivierten Mitglieder',
        rollen: ['Aufsicht', 'Kiosk'],
        e2e: 'session-device.spec.ts',
    },
    {
        id: 'S6',
        bereich: 'Am Stand',
        ablauf: 'Mitglied trägt am Kiosk mit eigener PIN ein → Aufsicht sieht den Eintrag „über Kiosk“',
        rollen: ['Mitglied', 'Kiosk', 'Aufsicht'],
        e2e: 'session-device.spec.ts',
    },
    {
        id: 'S7',
        bereich: 'Am Stand',
        ablauf: 'Aufsicht erfasst selbst für ein Mitglied',
        rollen: ['Aufsicht', 'Mitglied'],
        e2e: 'flows.spec.ts',
    },
    {
        id: 'S8',
        bereich: 'Am Stand',
        ablauf: 'Aufsicht nimmt einen Gastschützen ohne Konto auf (nur mit Bestätigung „volljährig“)',
        rollen: ['Aufsicht', 'Gastschütze'],
        e2e: 'more.spec.ts',
    },
    {
        id: 'S9',
        bereich: 'Am Stand',
        ablauf: 'Aufsicht lehnt einen Eintrag mit Grund ab → Mitglied sieht Ablehnung und Grund',
        rollen: ['Aufsicht', 'Mitglied'],
        e2e: 'session-device.spec.ts',
    },
    {
        id: 'S10',
        bereich: 'Am Stand',
        ablauf: 'Mitglied zieht den eigenen Eintrag zurück, solange nicht abgezeichnet ist',
        rollen: ['Mitglied', 'Aufsicht'],
    },
    {
        id: 'S11',
        bereich: 'Am Stand',
        ablauf: 'Aufsicht zeichnet ab → Kiosk zeigt „Session beendet“, Einträge sind fest',
        rollen: ['Aufsicht', 'Kiosk', 'Mitglied'],
        e2e: 'session-device.spec.ts',
    },

    // Kladde
    {
        id: 'K1',
        bereich: 'Kladde',
        ablauf: 'Mitglied sieht die abgezeichneten Schüsse in der eigenen Schießkladde und lädt den Auszug',
        rollen: ['Mitglied'],
        e2e: 'more.spec.ts',
    },
    {
        id: 'K2',
        bereich: 'Kladde',
        ablauf: 'Vorstand sieht den Eintrag in der Kladde des Vereins und das Session-Protokoll',
        rollen: ['Vorstand'],
        e2e: 'flows.spec.ts',
    },
    {
        id: 'K3',
        bereich: 'Kladde',
        ablauf: 'Vorstand korrigiert einen abgezeichneten Eintrag mit Passwort und Grund → Mitglied sieht den neuen Wert',
        rollen: ['Vorstand', 'Mitglied'],
    },
    {
        id: 'K4',
        bereich: 'Kladde',
        ablauf: 'Vorstand storniert einen Eintrag → er zählt nicht mehr, bleibt aber nachvollziehbar',
        rollen: ['Vorstand', 'Mitglied'],
    },
    {
        id: 'K5',
        bereich: 'Kladde',
        ablauf: 'Vorstand trägt einen vergessenen Besuch nach → Mitglied sieht ihn in der Kladde',
        rollen: ['Vorstand', 'Mitglied'],
        e2e: 'flows.spec.ts',
    },
    {
        id: 'K6',
        bereich: 'Kladde',
        ablauf: 'Jede Änderung steht im Protokoll, mit Person, Zeit und Grund',
        rollen: ['Vorstand'],
    },
    {
        id: 'K7',
        bereich: 'Kladde',
        ablauf: 'Vorstand sieht die Statistik und lädt die Nachweise eines Mitglieds',
        rollen: ['Vorstand'],
    },

    // Verwaltung
    {
        id: 'V1',
        bereich: 'Verwaltung',
        ablauf: 'Vorstand trägt ein Mitglied ins Aufsichtsregister ein → es kann eine Session starten',
        rollen: ['Vorstand', 'Aufsicht'],
        e2e: 'flows.spec.ts',
    },
    {
        id: 'V2',
        bereich: 'Verwaltung',
        ablauf: 'Vorstand löscht die Kiosk-PIN eines Mitglieds → am Kiosk geht es nur noch über die Aufsicht',
        rollen: ['Vorstand', 'Mitglied', 'Kiosk'],
    },
    {
        id: 'V3',
        bereich: 'Verwaltung',
        ablauf: 'Vorstand deaktiviert ein Mitglied → keine Anmeldung mehr, der Auszug bleibt abrufbar',
        rollen: ['Vorstand', 'Mitglied'],
        e2e: 'more.spec.ts',
    },
    {
        id: 'V4',
        bereich: 'Verwaltung',
        ablauf: 'Vorstand pflegt Impressum und Vereinsangaben → Gast sieht sie öffentlich',
        rollen: ['Vorstand', 'Gast'],
        e2e: 'more.spec.ts',
    },
    {
        id: 'V5',
        bereich: 'Verwaltung',
        ablauf: 'Registereintrag abgelaufen oder entzogen → Aufsicht kann nicht mehr eintragen',
        rollen: ['Vorstand', 'Aufsicht'],
        e2e: 'more.spec.ts',
    },

    // Rückmeldung
    {
        id: 'R1',
        bereich: 'Rückmeldung',
        ablauf: 'Mitglied meldet einen Fehler mit Bildschirmfoto → Vorstand sieht Meldung und Bild und schließt sie ab',
        rollen: ['Mitglied', 'Vorstand'],
        e2e: 'help.spec.ts',
    },

    // Grenzen
    {
        id: 'G1',
        bereich: 'Grenzen',
        ablauf: 'Gast ruft eine geschützte Seite auf → landet bei der Anmeldung',
        rollen: ['Gast'],
    },
    {
        id: 'G2',
        bereich: 'Grenzen',
        ablauf: 'Mitglied ruft die Verwaltung auf → Zugriff verweigert, kein Menüpunkt',
        rollen: ['Mitglied'],
    },
    {
        id: 'G3',
        bereich: 'Grenzen',
        ablauf: 'Mitglied ohne Registereintrag am Stand → kann keine Session starten',
        rollen: ['Mitglied'],
        e2e: 'flows.spec.ts',
    },
    {
        id: 'G4',
        bereich: 'Grenzen',
        ablauf: 'Antragsteller und deaktiviertes Mitglied → Anmeldung mit Hinweis abgewiesen',
        rollen: ['Antragsteller', 'Mitglied'],
        e2e: 'application.spec.ts',
    },
    {
        id: 'G5',
        bereich: 'Grenzen',
        ablauf: 'Kiosk: falsche PIN abgewiesen, Mitglied ohne PIN nur über die Aufsicht',
        rollen: ['Kiosk', 'Mitglied'],
        e2e: 'session-device.spec.ts',
    },
    {
        id: 'G6',
        bereich: 'Grenzen',
        ablauf: 'Jugendlicher ohne PIN und ohne Volljährigkeitsvermerk → Aufsicht muss erfassen und prüfen',
        rollen: ['Jugendlicher', 'Aufsicht', 'Kiosk'],
    },
    {
        id: 'G7',
        bereich: 'Grenzen',
        ablauf: 'Abgezeichnete Einträge kann weder Mitglied noch Aufsicht ändern, nur der Vorstand mit Passwort',
        rollen: ['Mitglied', 'Aufsicht', 'Vorstand'],
    },
    {
        id: 'G8',
        bereich: 'Grenzen',
        ablauf: 'Aufsicht nur für erlaubnisfreie Waffen → keine Aufsicht für Kurzwaffe',
        rollen: ['Vorstand', 'Aufsicht'],
        e2e: 'flows.spec.ts',
    },
];
