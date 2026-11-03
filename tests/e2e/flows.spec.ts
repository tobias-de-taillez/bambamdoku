import { expect, test, type Page } from '@playwright/test';
import {
    completeEntry,
    failOnConsoleErrors,
    login,
    startSession,
} from './helpers';

/** Disziplin wählen, falls der Verein einen Katalog eingestellt hat. */
async function pickDisciplineIfAsked(page: Page, id: string): Promise<void> {
    const select = page.locator(`#${id}`);
    if ((await select.count()) > 0 && (await select.inputValue()) === '') {
        await select.selectOption({ index: 1 });
    }
}

function yesterday(): string {
    const d = new Date(Date.now() - 86400000);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

test('Aufsicht startet die Session, erfasst ein Mitglied, Doppel-Tap legt nichts doppelt an, Ablehnen mit Grund, Abzeichnen', async ({
    page,
}) => {
    const errors = failOnConsoleErrors(page);
    await login(page, 'helga.schiesswart@demo.test');

    await page.goto('/stand');
    await startSession(page);
    await page.getByRole('button', { name: 'Eintragen' }).first().click();
    await page.getByLabel('Mitglied suchen').fill('1004');
    await page.getByRole('button', { name: /Max Mustermann/ }).click();
    await pickDisciplineIfAsked(page, 'discipline');
    await completeEntry(page);
    await page
        .locator('form')
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();

    await expect(
        page.getByRole('status').filter({ hasText: 'erfasst um' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Teilnehmer dieser Session' }),
    ).toBeVisible();

    // Gleiche Person noch einmal: „schon erfasst“, kein zweiter Eintrag.
    await page.getByLabel('Mitglied suchen').fill('1004');
    await page.getByRole('button', { name: /Max Mustermann/ }).click();
    await pickDisciplineIfAsked(page, 'discipline');
    await completeEntry(page);
    await page
        .locator('form')
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();
    await expect(page.getByText(/schon erfasst \(30 Schüsse\)/)).toBeVisible();

    const max = page
        .locator('section[aria-labelledby="participants"] > ul > li')
        .filter({ hasText: 'Max Mustermann' });
    await expect(max.locator('ul > li')).toHaveCount(1);

    // Ablehnen mit Schnellgrund
    await max.getByRole('button', { name: 'Ablehnen' }).click();
    await max.getByRole('button', { name: 'Falsche Kategorie' }).click();
    await max
        .getByRole('button', { name: 'Ablehnen', exact: true })
        .last()
        .click();
    await expect(max).toContainText('Grund: Falsche Kategorie');

    // neu erfassen und abzeichnen
    await max.getByRole('button', { name: 'Erfassen' }).click();
    await pickDisciplineIfAsked(page, 'discipline');
    await completeEntry(page, '25');
    await page
        .locator('form')
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();
    await expect(max.getByText('25 Schüsse')).toBeVisible();
    await page.getByRole('button', { name: 'Abzeichnen' }).first().click();
    await expect(
        page.locator('section[aria-labelledby="sign-off-title"] li'),
    ).toHaveCount(1);
    await expect(
        page.getByText(/unter meiner Aufsicht geschossen/),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Bestätigen und abzeichnen' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Heute abgezeichnet' }),
    ).toBeVisible();

    expect(errors).toEqual([]);
});

test('Ohne Registereintrag kein Eintragen', async ({ page }) => {
    await login(page, 'max.mustermann@demo.test');
    await page.goto('/stand');

    await expect(
        page.getByText(
            'Eine Session starten dürfen nur Personen mit gültiger Zeile im Aufsichtsregister',
        ),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Eintragen' })).toHaveCount(
        0,
    );
});

test('Mitglied bucht eine Bahn', async ({ page }) => {
    const errors = failOnConsoleErrors(page);
    await login(page, 'erika.musterfrau@demo.test');

    await page.goto('/schiesstage');
    await page.locator('a[href*="/schiesstage/"]').first().click();
    const free = page.locator('table button:not([disabled])').first();
    await free.click();
    const weapon = page.getByLabel('Waffenart');
    if ((await weapon.inputValue()) === '') {
        await weapon.selectOption({ index: 1 });
    }
    await page.getByRole('button', { name: /Uhr buchen/ }).click();

    await expect(
        page.getByRole('heading', { name: 'Ihre Buchungen an diesem Tag' }),
    ).toBeVisible();
    expect(errors).toEqual([]);
});

test('Vorstand: Kladde, Nachtrag und Auszug', async ({ page }) => {
    const errors = failOnConsoleErrors(page);
    await login(page, 'petra.vorstand@demo.test');

    await page.goto('/verwaltung/schiesskladde');
    await expect(
        page.getByRole('heading', { name: 'Schießkladde des Vereins' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Nachtrag' }).click();
    await page.locator('#late-filter').fill('Ben');
    await page.getByRole('checkbox').first().click();
    await page.locator('#late-date').fill(yesterday());
    await pickDisciplineIfAsked(page, 'late-discipline');
    await page
        .locator('#late-reason')
        .fill('Papierliste vom Vortag, Aufsicht: Jonas Aufsicht');
    await page.getByRole('button', { name: '1 nachtragen' }).click();
    await expect(page.getByText('1 nachgetragen')).toBeVisible();

    const download = page.waitForEvent('download');
    await page.locator('#member').selectOption({ label: 'Max Mustermann' });
    await page.getByRole('button', { name: 'Anzeigen' }).click();
    await page.getByRole('link', { name: /Auszug Max Mustermann/ }).click();
    expect((await download).suggestedFilename()).toMatch(
        /^Schiesskladde-max-mustermann/,
    );

    expect(errors).toEqual([]);
});

test('Vorstand legt Mitglied ohne Konto an und registriert eine Aufsicht für erlaubnisfreie Waffen', async ({
    page,
    browser,
}) => {
    const errors = failOnConsoleErrors(page);
    await login(page, 'petra.vorstand@demo.test');

    await page.goto('/verwaltung/mitglieder/neu');
    await page.locator('#name').fill('Nora Neu');
    await page.locator('#member_number').fill('2001');
    await page.getByRole('button', { name: 'Anlegen', exact: true }).click();
    await expect(page.getByRole('link', { name: 'Nora Neu' })).toBeVisible();

    await page.getByRole('link', { name: 'Karl Kurzwaffe' }).click();
    await page.getByRole('button', { name: 'Prüfung eintragen' }).click();
    await page.locator('#reg-scope').selectOption('exempt');
    await page.locator('#reg-until').fill('2030-12-31');
    await page.getByRole('checkbox', { name: /Ich habe geprüft/ }).click();
    await page
        .locator('section[aria-labelledby="register"]')
        .getByRole('button', { name: 'Eintragen' })
        .click();
    await expect(
        page.locator('section[aria-labelledby="register"] li').first(),
    ).toContainText('aktuell');
    expect(errors).toEqual([]);

    // Karl darf jetzt eintragen, aber nur erlaubnisfrei.
    const karl = await (await browser.newContext()).newPage();
    await login(karl, 'karl.kurzwaffe@demo.test');
    await karl.goto('/stand');
    await startSession(karl);
    await karl.getByRole('button', { name: 'Eintragen' }).first().click();
    await expect(
        karl.getByRole('button', { name: 'Kurzwaffe', exact: true }),
    ).toBeDisabled();
    await karl.getByLabel('Mitglied suchen').fill('2001');
    await karl.getByRole('button', { name: /Nora Neu/ }).click();
    await pickDisciplineIfAsked(karl, 'discipline');
    await completeEntry(karl);
    await karl
        .locator('form')
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();
    const nora = karl
        .locator('section[aria-labelledby="participants"] > ul > li')
        .filter({ hasText: 'Nora Neu' });
    await expect(nora).toContainText('erlaubnisfrei');
});
