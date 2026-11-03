import { expect, test, type Browser, type Page } from '@playwright/test';
import { login, startSession } from '../e2e/helpers';

/**
 * Bilder für den Demo-Bereich der Startseite, aufgenommen mit den Demo-Daten (DemoSeeder).
 * Neu aufnehmen: npm run screenshots:startseite
 * Ablage: public/images/startseite/<ansicht>-<hell|dunkel>.png (das npm-Skript wandelt in WebP).
 */

const OUT = 'public/images/startseite';
const SCHEMES = [
    ['light', 'hell'],
    ['dark', 'dunkel'],
] as const;

async function hideHelpButton(page: Page): Promise<void> {
    await page.addStyleTag({
        content: '[data-help-root]{display:none!important}',
    });
}

async function activate(page: Page, name: string): Promise<void> {
    await page.getByLabel('Mitglied aktivieren').fill(name.split(' ')[0]);
    await page.getByRole('button', { name: new RegExp(`^${name}`) }).click();
    await expect(
        page.getByRole('button', { name: `${name} aus der Session entfernen` }),
    ).toBeVisible();
}

async function kioskUrl(browser: Browser): Promise<string> {
    const helga = await (await browser.newContext()).newPage();
    await login(helga, 'helga.schiesswart@demo.test');
    await helga.goto('/stand');
    await startSession(helga);

    for (const name of [
        'Max Mustermann',
        'Erika Musterfrau',
        'Karl Kurzwaffe',
        'Lena Langwaffe',
    ]) {
        await activate(helga, name);
    }

    await helga.getByRole('button', { name: 'Kiosk', exact: true }).click();
    await helga
        .getByRole('button', { name: 'Link für ein anderes Gerät' })
        .click();
    const url = (
        await helga.locator('[data-test="kiosk-url"]').textContent()
    )?.trim();
    expect(url).toContain('/kiosk/');

    return url!;
}

test('Startseite: Kiosk am Stand und Planung des Vereins', async ({
    browser,
}) => {
    test.setTimeout(120_000);
    const url = await kioskUrl(browser);

    for (const [scheme, suffix] of SCHEMES) {
        // Kiosk: Tablet quer, ohne Anmeldung, nur die aktivierten Mitglieder.
        const tablet = await (
            await browser.newContext({
                viewport: { width: 1024, height: 768 },
                deviceScaleFactor: 2,
                colorScheme: scheme,
            })
        ).newPage();
        await tablet.goto(url);
        await tablet.getByRole('button', { name: /Max Mustermann/ }).click();
        for (const digit of '1234') {
            await tablet
                .getByRole('group', { name: 'Zifferntastatur' })
                .getByRole('button', { name: digit, exact: true })
                .click();
        }
        await tablet.getByRole('button', { name: 'Weiter' }).click();
        await expect(tablet.getByText('Ihr Eintrag für heute')).toBeVisible();
        // Kategorie und Waffe sind aus dem letzten Termin vorbelegt; nur die Schüsse fehlen.
        await tablet.getByLabel('Schüsse').fill('30');
        await tablet.getByLabel('Schüsse').blur();
        await tablet.mouse.move(0, 0);
        await hideHelpButton(tablet);
        // Nur die Spalte des Formulars, ohne den leeren Rand des Tablets.
        const column = await tablet.locator('main').boundingBox();
        const actions = await tablet
            .getByRole('button', { name: 'Erfassen' })
            .boundingBox();
        await tablet.screenshot({
            path: `${OUT}/kiosk-${suffix}.png`,
            clip: {
                x: column!.x,
                y: 0,
                width: column!.width,
                height: Math.ceil(actions!.y + actions!.height + 32),
            },
        });
        await tablet.context().close();

        // Verein: Schießtage planen und Buchungen im Blick, als Vorstand.
        const petra = await (
            await browser.newContext({
                viewport: { width: 1280, height: 900 },
                deviceScaleFactor: 2,
                colorScheme: scheme,
            })
        ).newPage();
        await login(petra, 'petra.vorstand@demo.test');
        await petra.goto('/verwaltung/schiesstage');
        await petra.waitForLoadState('networkidle');
        await petra.mouse.move(1270, 890);
        await hideHelpButton(petra);
        // Inhalt ohne Seitenleiste, damit die Schrift im Bild lesbar bleibt; Schnitt mit der Trennlinie unter dem vierten Termin.
        const main = petra.locator('div.max-w-4xl').first();
        const content = await main.boundingBox();
        const fourth = await main.locator('ul > li').nth(3).boundingBox();
        await petra.screenshot({
            path: `${OUT}/planung-${suffix}.png`,
            clip: {
                x: content!.x,
                y: content!.y,
                width: content!.width,
                height: Math.ceil(fourth!.y + fourth!.height + 1 - content!.y),
            },
        });
        await petra.context().close();
    }
});
