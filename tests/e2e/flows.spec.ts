import { expect, test, type Page } from '@playwright/test';

const PASSWORD = 'demo-passwort';

async function login(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('E-Mail-Adresse').fill(email);
    await page.getByLabel('Passwort', { exact: true }).fill(PASSWORD);
    await page.locator('[data-test="login-button"]').click();
    await expect(page).toHaveURL(/dashboard/);
}

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

function failOnConsoleErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });

    return errors;
}

test('Aufsicht trägt ein Mitglied ein, Doppel-Tap erzeugt keinen zweiten Eintrag, Storno mit Grund', async ({
    page,
}) => {
    const errors = failOnConsoleErrors(page);
    await login(page, 'helga.schiesswart@demo.test');

    await page.goto('/stand');
    await page.getByRole('button', { name: 'Eintragen' }).first().click();
    await page.getByLabel('Mitglied suchen').fill('1004');
    await page.getByRole('button', { name: /Max Mustermann/ }).click();
    await pickDisciplineIfAsked(page, 'discipline');
    await page
        .getByRole('button', { name: 'Eintragen', exact: true })
        .last()
        .click();

    await expect(
        page.getByRole('status').filter({ hasText: 'eingetragen um' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Heute eingetragen' }),
    ).toBeVisible();

    // Gleiche Person noch einmal: „heute schon eingetragen“, kein zweiter Eintrag.
    await page.getByLabel('Mitglied suchen').fill('1004');
    await page.getByRole('button', { name: /Max Mustermann/ }).click();
    await pickDisciplineIfAsked(page, 'discipline');
    await page
        .getByRole('button', { name: 'Eintragen', exact: true })
        .last()
        .click();
    await expect(page.getByText('ist heute schon eingetragen')).toBeVisible();

    const today = page.locator('section[aria-labelledby="today"] li');
    await expect(today).toHaveCount(1);

    await today.first().getByRole('button', { name: 'Storno' }).click();
    await today.first().getByRole('button', { name: 'Stornieren' }).click();
    await expect(today.first()).toContainText('storniert: Fehleingabe');

    expect(errors).toEqual([]);
});

test('Ohne Registereintrag kein Eintragen', async ({ page }) => {
    await login(page, 'max.mustermann@demo.test');
    await page.goto('/stand');

    await expect(
        page.getByText(
            'Eintragen dürfen nur Personen mit gültiger Zeile im Aufsichtsregister',
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
