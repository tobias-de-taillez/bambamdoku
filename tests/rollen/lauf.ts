import { copyFileSync, rmSync } from 'node:fs';

/**
 * Pfade und Port eines Laufs der Rollen-Suite. Über ROLLEN_LAUF und ROLLEN_PORT
 * laufen mehrere Läufe nebeneinander, ohne sich Datenbank oder Bericht zu teilen.
 */
export const LAUF = process.env.ROLLEN_LAUF ?? 'rollen';
export const PORT = Number(process.env.ROLLEN_PORT ?? 8223);

export const DB = `database/${LAUF}.sqlite`;
export const VORLAGE = `database/${LAUF}.vorlage.sqlite`;
export const ORDNER = `test-results/${LAUF}`;
export const ARTEFAKTE = `${ORDNER}/artefakte`;
export const BERICHT = `${ORDNER}/bericht`;

/** Zwischen 23:30 und Mitternacht ruht der Rundgang: Termine von heute wären vorbei, offene Einträge verfallen. */
export const NACHTPAUSE =
    'Nachtpause: zwischen 23:30 und Mitternacht läuft der Rollen-Rundgang nicht.';

/** Jede Geschichte beginnt mit dem frisch geseedeten Demo-Verein (Kopie der Vorlage). */
export function frischerVerein(): void {
    for (const suffix of ['-wal', '-shm', '-journal']) {
        rmSync(DB + suffix, { force: true });
    }

    copyFileSync(VORLAGE, DB);
}
