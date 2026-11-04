import { copyFileSync, rmSync } from 'node:fs';

/**
 * Pfade und Port eines Laufs der Rollen-Suite. Über ROLLEN_LAUF und ROLLEN_PORT
 * laufen mehrere Läufe nebeneinander, ohne sich Datenbank oder Bericht zu teilen.
 */
export const LAUF = process.env.ROLLEN_LAUF ?? 'rollen';
export const PORT = Number(process.env.ROLLEN_PORT ?? 8223);

export const DB = `database/${LAUF}.sqlite`;
export const VORLAGE = `database/${LAUF}.vorlage.sqlite`;
// Eigener Ordner: `npm run test:e2e` leert test-results/ beim Start.
export const ORDNER = `test-results-rollen/${LAUF}`;
export const ARTEFAKTE = `${ORDNER}/artefakte`;
export const BERICHT = `${ORDNER}/bericht`;

/** Mit ROLLEN_TERMIN_HEUTE=1 gibt es heute einen Demo-Termin bis Mitternacht (global-setup.ts). */
export const TERMIN_HEUTE = process.env.ROLLEN_TERMIN_HEUTE === '1';

/**
 * Kurz vor Mitternacht ruht der Rundgang: Termine von heute wären vorbei, offene Einträge
 * verfallen. Mit dem Demo-Termin schon ab 23 Uhr, weil dessen Buchungen um 22 Uhr beginnen.
 */
export const NACHTPAUSE_AB = TERMIN_HEUTE ? 23 * 60 : 23 * 60 + 30;
export const NACHTPAUSE = `Nachtpause: zwischen ${TERMIN_HEUTE ? '23:00' : '23:30'} und Mitternacht läuft der Rollen-Rundgang nicht.`;

/** Minuten seit Mitternacht in Europe/Berlin. */
export function minutenInBerlin(): number {
    const [stunde, minute] = new Date()
        .toLocaleTimeString('de-DE', {
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
            timeZone: 'Europe/Berlin',
        })
        .split(':')
        .map(Number);

    return stunde * 60 + minute;
}

export function inNachtpause(): boolean {
    return minutenInBerlin() >= NACHTPAUSE_AB;
}

/**
 * Ob global-setup.ts den Demo-Termin anlegt. Dessen erste Buchungen laufen 22–23 Uhr und
 * scheitern ab 23 Uhr („Diese Zeit ist schon vorbei.“), und zwischen dieser Prüfung und tinker
 * vergehen Sekunden. Darum fünf Minuten vorher nicht mehr: Bis zur Nachtpause laufen die
 * Geschichten dann wie an einem Tag ohne Demo-Termin, und das tragen sie alle.
 */
export function demoTerminAnlegen(): boolean {
    return TERMIN_HEUTE && minutenInBerlin() < 23 * 60 - 5;
}

/** Jede Geschichte beginnt mit dem frisch geseedeten Demo-Verein (Kopie der Vorlage). */
export function frischerVerein(): void {
    for (const suffix of ['-wal', '-shm', '-journal']) {
        rmSync(DB + suffix, { force: true });
    }

    copyFileSync(VORLAGE, DB);
}
