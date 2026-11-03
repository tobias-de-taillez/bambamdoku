import {
    test as base,
    expect,
    type Browser,
    type BrowserContextOptions,
    type Locator,
    type Page,
} from '@playwright/test';
import { frischerVerein } from './lauf';
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
 * auf einer der beteiligten Seiten.
 */
export const test = base.extend<{ verein: void }>({
    verein: [
        // Playwright liest die Fixtures aus dem Muster, das leere {} ist Pflicht.
        // eslint-disable-next-line no-empty-pattern
        async ({}, use) => {
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

/** Heutiges Datum, wie die App es zeigt (TT.MM.JJJJ, Europe/Berlin). */
export function heute(): string {
    return new Date().toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'Europe/Berlin',
    });
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

export async function einloggen(
    page: Page,
    email: string,
    passwort = PASSWORD,
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('E-Mail-Adresse').fill(email);
    await page.getByLabel('Passwort', { exact: true }).fill(passwort);
    await page.locator('[data-test="login-button"]').click();
    await expect(page).toHaveURL(/dashboard/);
}

/** Die Rolle einer Person ändert sich im Lauf der Geschichte, etwa vom Gast zum Mitglied. */
export function wirdZu(page: Page, person: Person): void {
    PERSON.set(page, person);
}

/**
 * Foto als Beleg: prüft zuerst `zeige` (sichtbar), markiert es und fotografiert dann den
 * Bildschirm der Person. Fotografieren soll die Rolle, die die Wirkung sieht.
 * `belegt` nennt die Zeilen der Matrix, die dieses Foto belegt.
 */
export async function beweis(
    page: Page,
    text: string,
    optionen: { zeige?: Locator; belegt?: string[]; ganzeSeite?: boolean } = {},
): Promise<void> {
    const person = PERSON.get(page);

    if (!person) {
        throw new Error('Seite ohne Person: erst oeffnen() oder anmelden()');
    }

    const belegt = optionen.belegt ?? [];
    const unbekannt = belegt.filter(
        (id) => !MATRIX.some((zeile) => zeile.id === id),
    );

    if (unbekannt.length > 0) {
        throw new Error(
            `Unbekannte Kennung in der Matrix: ${unbekannt.join(', ')}`,
        );
    }

    await test.step(`${person.name} (${person.rolle}): ${text}`, async () => {
        const zeige = optionen.zeige;

        if (zeige) {
            await expect(zeige).toBeVisible();
            await zeige.scrollIntoViewIfNeeded();
        }

        await page.evaluate(() => document.fonts.ready);
        const rahmen = zeige ? await zeige.boundingBox() : null;
        // Toasts und der schwebende Meldeknopf verdecken sonst oft genau das Geprüfte;
        // sichtbar bleiben sie nur, wenn sie selbst der Beleg sind.
        const verbergen = zeige
            ? await zeige.evaluate((el) =>
                  ['[data-help-root]', '[data-sonner-toaster]'].filter(
                      (sel) => el.closest(sel) === null,
                  ),
              )
            : ['[data-help-root]', '[data-sonner-toaster]'];

        if (rahmen) {
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

        const pfad = test
            .info()
            .outputPath(`beweis-${String(++zaehler).padStart(2, '0')}.png`);
        await page.screenshot({
            path: pfad,
            fullPage: optionen.ganzeSeite ?? false,
            animations: 'disabled',
            caret: 'hide',
            style: verbergen.length
                ? `${verbergen.join(',')}{display:none!important}`
                : undefined,
        });

        if (rahmen) {
            await page.evaluate(() =>
                document.querySelector('[data-beweis-rahmen]')?.remove(),
            );
        }

        await test.info().attach(
            JSON.stringify({
                beweis: true,
                person: person.name,
                rolle: person.rolle,
                geraet: person.geraet,
                text,
                belegt,
            }),
            { path: pfad, contentType: 'image/png' },
        );
    });
}
