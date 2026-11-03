import { readFileSync } from 'node:fs';
import { inflateSync } from 'node:zlib';
import {
    test as base,
    expect,
    type Browser,
    type BrowserContextOptions,
    type Download,
    type Locator,
    type Page,
} from '@playwright/test';
import { frischerVerein, NACHTPAUSE } from './lauf';
import { MATRIX, type Rolle } from './matrix';

export { expect };

export const PASSWORD = 'demo-passwort';
export const KIOSK_PIN = '1234';

export type Geraet = 'handy' | 'tablet' | 'desktop';

export type Person = {
    name: string;
    rolle: Rolle;
    geraet: Geraet;
    email?: string;
};

/** Die Personen des Demo-Vereins (DemoSeeder), jeweils mit dem Gerät, das sie typischerweise nutzen. */
export const PERSONEN = {
    petra: {
        name: 'Petra Vorstand',
        rolle: 'Vorstand',
        geraet: 'desktop',
        email: 'petra.vorstand@demo.test',
    },
    helga: {
        name: 'Helga Schießwart',
        rolle: 'Aufsicht',
        geraet: 'tablet',
        email: 'helga.schiesswart@demo.test',
    },
    jonas: {
        name: 'Jonas Aufsicht',
        rolle: 'Aufsicht',
        geraet: 'handy',
        email: 'jonas.aufsicht@demo.test',
    },
    max: {
        name: 'Max Mustermann',
        rolle: 'Mitglied',
        geraet: 'handy',
        email: 'max.mustermann@demo.test',
    },
    erika: {
        name: 'Erika Musterfrau',
        rolle: 'Mitglied',
        geraet: 'handy',
        email: 'erika.musterfrau@demo.test',
    },
    karl: {
        name: 'Karl Kurzwaffe',
        rolle: 'Mitglied',
        geraet: 'handy',
        email: 'karl.kurzwaffe@demo.test',
    },
    lena: {
        name: 'Lena Langwaffe',
        rolle: 'Mitglied',
        geraet: 'handy',
        email: 'lena.langwaffe@demo.test',
    },
    ben: {
        name: 'Ben Bogen',
        rolle: 'Mitglied',
        geraet: 'handy',
        email: 'ben.bogen@demo.test',
    },
    tim: {
        name: 'Tim Jungschütze',
        rolle: 'Jugendlicher',
        geraet: 'handy',
        email: 'tim.jungschutze@demo.test',
    },
    anna: {
        name: 'Anna Antrag',
        rolle: 'Antragsteller',
        geraet: 'handy',
        email: 'anna.antrag@demo.test',
    },
} as const satisfies Record<string, Person>;

export const KIOSK: Person = {
    name: 'Kiosk-Tablet',
    rolle: 'Kiosk',
    geraet: 'tablet',
};

const GERAETE: Record<Geraet, BrowserContextOptions> = {
    handy: {
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
    },
    tablet: { viewport: { width: 1024, height: 768 }, hasTouch: true },
    desktop: { viewport: { width: 1280, height: 800 } },
};

const PERSON = new WeakMap<Page, Person>();
let seiten: Page[] = [];
let fehler: string[] = [];
let zaehler = 0;

/**
 * Jede Geschichte beginnt mit dem frischen Demo-Verein und endet ohne JavaScript-Fehler
 * auf einer der beteiligten Seiten. In der Nachtpause wird sie übersprungen.
 */
export const test = base.extend<{ verein: void }>({
    verein: [
        // Playwright liest die Fixtures aus dem Muster, das leere {} ist Pflicht.
        // eslint-disable-next-line no-empty-pattern
        async ({}, use, testInfo) => {
            testInfo.skip(minutenInBerlin() >= 23 * 60 + 30, NACHTPAUSE);
            frischerVerein();
            seiten = [];
            fehler = [];
            zaehler = 0;
            await use();

            for (const seite of seiten) {
                await seite.context().close();
            }

            expect(fehler, 'JavaScript-Fehler auf einer Seite').toEqual([]);
        },
        { auto: true },
    ],
});

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

/** Ein Tag relativ zu heute (Europe/Berlin): ISO für Datumsfelder, TT.MM.JJJJ wie die App ihn zeigt. */
export function tag(versatz = 0): { iso: string; anzeige: string } {
    const heuteIso = new Date().toLocaleDateString('sv-SE', {
        timeZone: 'Europe/Berlin',
    });
    const datum = new Date(`${heuteIso}T12:00:00Z`);
    datum.setUTCDate(datum.getUTCDate() + versatz);

    return {
        iso: datum.toISOString().slice(0, 10),
        anzeige: datum.toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            timeZone: 'UTC',
        }),
    };
}

/** Heutiges Datum, wie die App es zeigt (TT.MM.JJJJ, Europe/Berlin). */
export function heute(): string {
    return tag(0).anzeige;
}

/** Laufendes Jahr in Europe/Berlin. */
export function jahr(): number {
    return Number(tag(0).iso.slice(0, 4));
}

/** Eigener Browser-Kontext für eine Person, auf ihrem Gerät, noch nicht angemeldet. */
export async function oeffnen(browser: Browser, person: Person): Promise<Page> {
    const page = await (
        await browser.newContext(GERAETE[person.geraet])
    ).newPage();
    page.on('pageerror', (error) =>
        fehler.push(`${person.name}: ${error.message}`),
    );
    PERSON.set(page, person);
    seiten.push(page);

    return page;
}

/** Person öffnen und über das Anmeldeformular anmelden. */
export async function anmelden(
    browser: Browser,
    person: Person,
): Promise<Page> {
    if (!person.email) {
        throw new Error(`${person.name} hat kein Konto`);
    }

    const page = await oeffnen(browser, person);
    await einloggen(page, person.email);

    return page;
}

/** Anmeldeformular abschicken, ohne eine erfolgreiche Anmeldung zu erwarten. */
export async function anmeldenVersuchen(
    page: Page,
    email: string,
    passwort = PASSWORD,
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('E-Mail-Adresse').fill(email);
    await page.getByLabel('Passwort', { exact: true }).fill(passwort);
    await page.locator('[data-test="login-button"]').click();
}

export async function einloggen(
    page: Page,
    email: string,
    passwort = PASSWORD,
): Promise<void> {
    await anmeldenVersuchen(page, email, passwort);
    await expect(page).toHaveURL(/dashboard/);
}

/** /settings/security öffnen; vorher fragt die App das Passwort ab. */
export async function sicherheitOeffnen(
    page: Page,
    passwort = PASSWORD,
): Promise<void> {
    await page.goto('/settings/security');
    await expect(page).toHaveURL(/confirm-password/);
    await page.getByLabel('Passwort', { exact: true }).fill(passwort);
    await page.locator('[data-test="confirm-password-button"]').click();
    await expect(page).toHaveURL(/settings\/security/);
}

/** Die Rolle einer Person ändert sich im Lauf der Geschichte, etwa vom Gast zum Mitglied. */
export function wirdZu(page: Page, person: Person): void {
    PERSON.set(page, person);
}

/** Download über einen Link auslösen. */
export async function laden(page: Page, link: Locator): Promise<Download> {
    const download = page.waitForEvent('download');
    await link.click();

    return download;
}

const ESCAPES: Record<string, string> = {
    n: '\n',
    r: '\r',
    t: '\t',
    b: '\b',
    f: '\f',
};

/**
 * Text eines PDFs von dompdf: Inhaltsströme entpacken, die Zeichenketten der TJ-Befehle
 * als UTF-16BE lesen. Reicht, um zu prüfen, was in Auszug oder Protokoll steht.
 */
export async function pdfText(download: Download): Promise<string> {
    const pdf = readFileSync(await download.path()).toString('latin1');
    const teile: string[] = [];

    for (const [, strom] of pdf.matchAll(
        /stream\r?\n([\s\S]*?)\r?\nendstream/g,
    )) {
        let inhalt: string;

        try {
            inhalt = inflateSync(Buffer.from(strom, 'latin1')).toString(
                'latin1',
            );
        } catch {
            continue;
        }

        for (const [, roh] of inhalt.matchAll(/\[\(([\s\S]*?)\)\] TJ/g)) {
            const bytes = Buffer.from(
                roh.replace(
                    /\\([\\()nrtbf])/g,
                    (_, c: string) => ESCAPES[c] ?? c,
                ),
                'latin1',
            );

            if (bytes.length % 2 === 0) {
                teile.push(bytes.swap16().toString('utf16le'));
            }
        }
    }

    return teile.join(' ').replace(/\s+/g, ' ');
}

function pruefeKennungen(ids: string[]): void {
    const unbekannt = ids.filter(
        (id) => !MATRIX.some((zeile) => zeile.id === id),
    );

    if (unbekannt.length > 0) {
        throw new Error(
            `Unbekannte Kennung in der Matrix: ${unbekannt.join(', ')}`,
        );
    }
}

/**
 * Foto als Beleg: prüft zuerst `zeige` (sichtbar), holt es in die Bildmitte, markiert es
 * und fotografiert dann den Bildschirm der Person. Fotografieren soll die Rolle, die die
 * Wirkung sieht.
 *
 * - `belegt`: Zeilen der Matrix, die dieses Foto belegt.
 * - `befund`: Zeilen, bei denen das Foto eine Abweichung zeigt (bekannter Fehler, `offen` in
 *   der Matrix). Solche Fotos zählen nicht als Beleg, der Bericht hebt sie hervor.
 */
export async function beweis(
    page: Page,
    text: string,
    optionen: {
        zeige?: Locator;
        belegt?: string[];
        befund?: string[];
        ganzeSeite?: boolean;
    } = {},
): Promise<void> {
    const person = PERSON.get(page);

    if (!person) {
        throw new Error('Seite ohne Person: erst oeffnen() oder anmelden()');
    }

    const belegt = optionen.belegt ?? [];
    const befund = optionen.befund ?? [];
    pruefeKennungen([...belegt, ...befund]);

    await test.step(`${person.name} (${person.rolle}): ${text}`, async () => {
        const zeige = optionen.zeige;

        if (zeige) {
            await expect(zeige).toBeVisible();

            if (!optionen.ganzeSeite) {
                await zeige.evaluate((el) =>
                    el.scrollIntoView({ block: 'center', inline: 'nearest' }),
                );
            }
        }

        await page.evaluate(() => document.fonts.ready);

        // Ein Toast gleitet noch herein: erst messen, wenn er steht. Endlose Animationen
        // (Spinner) werden nicht abgewartet.
        if (zeige) {
            await zeige.evaluate((el) =>
                Promise.all(
                    (el.closest('[data-sonner-toast]') ?? el)
                        .getAnimations({ subtree: true })
                        .filter(
                            (a) =>
                                a.effect?.getComputedTiming().iterations !==
                                Infinity,
                        )
                        .map((a) => a.finished.catch(() => undefined)),
                ),
            );
        }

        // Toasts und der schwebende Meldeknopf verdecken sonst oft genau das Geprüfte;
        // sichtbar bleiben sie nur, wenn sie selbst der Beleg sind. In einem offenen
        // <dialog> (oberste Ebene) liegt ein Rahmen darunter, dort markiert ein Umriss.
        const lage = zeige
            ? await zeige.evaluate((el) => ({
                  verbergen: [
                      '[data-help-root]',
                      '[data-sonner-toaster]',
                  ].filter((sel) => el.closest(sel) === null),
                  imDialog: el.closest('dialog[open]') !== null,
              }))
            : {
                  verbergen: ['[data-help-root]', '[data-sonner-toaster]'],
                  imDialog: false,
              };
        const rahmen = zeige ? await zeige.boundingBox() : null;

        if (zeige && lage.imDialog) {
            await zeige.evaluate((el) => {
                el.dataset.beweisUmriss = el.style.outline;
                el.style.outline = '3px solid #e11d48';
                el.style.outlineOffset = '5px';
            });
        } else if (rahmen) {
            await page.evaluate((r) => {
                const box = document.createElement('div');
                box.dataset.beweisRahmen = '';
                Object.assign(box.style, {
                    position: 'absolute',
                    left: `${r.x + window.scrollX - 5}px`,
                    top: `${r.y + window.scrollY - 5}px`,
                    width: `${r.width + 10}px`,
                    height: `${r.height + 10}px`,
                    border: '3px solid #e11d48',
                    borderRadius: '8px',
                    pointerEvents: 'none',
                    zIndex: '2147483647',
                });
                document.documentElement.appendChild(box);
            }, rahmen);
        }

        const nr = String(++zaehler).padStart(2, '0');
        const pfad = test.info().outputPath(`beweis-${nr}.png`);
        await page.screenshot({
            path: pfad,
            fullPage: optionen.ganzeSeite ?? false,
            animations: 'disabled',
            caret: 'hide',
            style: lage.verbergen.length
                ? `${lage.verbergen.join(',')}{display:none!important}`
                : undefined,
        });

        if (zeige && lage.imDialog) {
            await zeige.evaluate((el) => {
                el.style.outline = el.dataset.beweisUmriss ?? '';
                el.style.outlineOffset = '';
                delete el.dataset.beweisUmriss;
            });
        } else if (rahmen) {
            await page.evaluate(() =>
                document.querySelector('[data-beweis-rahmen]')?.remove(),
            );
        }

        // Kurze Namen für die Anhänge: Playwright bildet daraus Dateinamen.
        await test.info().attach(`beweis-${nr}.png`, {
            path: pfad,
            contentType: 'image/png',
        });
        await test.info().attach(`beweis-${nr}.json`, {
            body: JSON.stringify({
                person: person.name,
                rolle: person.rolle,
                geraet: person.geraet,
                text,
                belegt,
                befund,
            }),
            contentType: 'application/json',
        });
    });
}
