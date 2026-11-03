import { expect, test, type Page } from '@playwright/test';
import { as } from './helpers';

/** Weitere Abläufe; jeder Test nutzt eigene Personen, damit sie sich nicht stören. */

async function openMember(admin: Page, name: string): Promise<void> {
    await admin.goto('/verwaltung/mitglieder');
    await admin.getByRole('link', { name, exact: true }).click();
    await expect(admin.locator('#name')).toHaveValue(name);
}

test('Gast nur mit Bestätigung „volljährig“; nach Registerentzug kein Eintragen mehr', async ({
    browser,
}) => {
    const jonas = await as(browser, 'jonas.aufsicht@demo.test');
    await jonas.goto('/stand');
    await jonas.getByRole('button', { name: 'Eintragen' }).first().click();
    await jonas.getByRole('tab', { name: 'Gast' }).click();
    await jonas.getByLabel('Name des Gastes').fill('Gerd Gast');
    await jonas
        .getByRole('button', { name: 'erlaubnisfrei', exact: true })
        .click();
    await jonas
        .getByRole('button', { name: 'Eintragen', exact: true })
        .last()
        .click();
    await expect(
        jonas.getByText('Bitte bestätigen, dass der Gast volljährig ist.'),
    ).toBeVisible();

    await jonas.getByText('Gast ist volljährig (Ausweis gesehen)').click();
    await jonas
        .getByRole('button', { name: 'Eintragen', exact: true })
        .last()
        .click();
    const entry = jonas
        .locator('section[aria-labelledby="today"] li')
        .filter({ hasText: 'Gerd Gast' });
    await expect(entry).toHaveCount(1);
    await expect(entry).toContainText('Gast');

    const petra = await as(browser, 'petra.vorstand@demo.test');
    await openMember(petra, 'Jonas Aufsicht');
    await petra.getByRole('button', { name: 'Prüfung eintragen' }).click();
    await petra.locator('#reg-scope').selectOption('revoked');
    await petra.locator('#reg-note').fill('Lehrgang nicht aufgefrischt');
    await petra
        .locator('section[aria-labelledby="register"]')
        .getByRole('button', { name: 'Eintragen' })
        .click();
    await expect(
        petra.locator('section[aria-labelledby="register"] li').first(),
    ).toContainText('aktuell');

    await jonas.goto('/stand');
    await expect(
        jonas.getByText(
            'Eintragen dürfen nur Personen mit gültiger Zeile im Aufsichtsregister',
        ),
    ).toBeVisible();
    await expect(jonas.getByRole('button', { name: 'Eintragen' })).toHaveCount(
        0,
    );
});

test('Einladungslink: Passwort festlegen und angemeldet sein', async ({
    browser,
}) => {
    const petra = await as(browser, 'petra.vorstand@demo.test');
    await petra.goto('/verwaltung/mitglieder/neu');
    await petra.locator('#name').fill('Ida Eingeladen');
    await petra.locator('#email').fill('ida.eingeladen@demo.test');
    await petra.getByRole('button', { name: 'Anlegen und einladen' }).click();
    const link = await petra.getByLabel('Einladungslink').inputValue();
    expect(link).toContain('http');

    const ida = await (await browser.newContext()).newPage();
    await ida.goto(link);
    await ida.locator('#password').fill('ein-sicheres-passwort-42');
    await ida
        .locator('#password_confirmation')
        .fill('ein-sicheres-passwort-42');
    await ida
        .getByRole('button', { name: 'Passwort festlegen und anmelden' })
        .click();
    await expect(ida).toHaveURL(/dashboard/);

    // Der Link funktioniert nur einmal.
    const again = await (await browser.newContext()).newPage();
    await again.goto(link);
    await expect(again).toHaveURL(/login/);
    await expect(
        again.getByText('Dieser Einladungslink ist nicht mehr gültig.'),
    ).toBeVisible();
});

test('Mitglied bucht und storniert die eigene Buchung', async ({ browser }) => {
    const lena = await as(browser, 'lena.langwaffe@demo.test');
    await lena.goto('/schiesstage');
    // Ein Termin ohne Buchung von Lena, mit Aufsicht
    await lena
        .getByRole('link')
        .filter({ hasText: 'Aufsicht:' })
        .filter({ hasNotText: 'Gebucht' })
        .first()
        .click();
    await expect(lena).toHaveURL(/schiesstage\/\d+/);
    await lena.locator('table button:not([disabled])').first().click();
    await lena.getByRole('button', { name: /Uhr buchen/ }).click();
    await expect(
        lena.getByRole('heading', { name: 'Ihre Buchungen an diesem Tag' }),
    ).toBeVisible();

    await lena.goto('/buchungen');
    const before = await lena
        .getByRole('button', { name: 'Stornieren' })
        .count();
    expect(before).toBeGreaterThan(0);
    await lena.getByRole('button', { name: 'Stornieren' }).first().click();
    await expect(lena.getByRole('button', { name: 'Stornieren' })).toHaveCount(
        before - 1,
    );
});

test('Mitglied sieht die eigene Kladde und lädt den Auszug', async ({
    browser,
}) => {
    const lena = await as(browser, 'lena.langwaffe@demo.test');
    await lena.goto('/schiesskladde');
    await expect(
        lena.getByRole('heading', {
            name: 'Meine Einträge in der Schießkladde',
        }),
    ).toBeVisible();
    await lena.getByRole('button', { name: 'letzte 24 Monate' }).click();
    await expect(lena.getByRole('heading', { name: 'Termine' })).toBeVisible();

    const download = lena.waitForEvent('download');
    await lena.getByRole('link', { name: 'Auszug zur Information' }).click();
    expect((await download).suggestedFilename()).toMatch(
        /^Schiesskladde-lena-langwaffe-/,
    );
});

test('Deaktiviertes Mitglied kann sich nicht anmelden, der Auszug bleibt abrufbar', async ({
    browser,
}) => {
    const petra = await as(browser, 'petra.vorstand@demo.test');
    await openMember(petra, 'Tim Jungschütze');
    await petra
        .getByText('– deaktivierte Mitglieder können sich nicht anmelden')
        .click();
    await petra.getByRole('button', { name: 'Speichern' }).click();
    await expect(petra).toHaveURL(/verwaltung\/mitglieder/);

    const tim = await (await browser.newContext()).newPage();
    await tim.goto('/login');
    await tim.getByLabel('E-Mail-Adresse').fill('tim.jungschutze@demo.test');
    await tim.getByLabel('Passwort', { exact: true }).fill('demo-passwort');
    await tim.locator('[data-test="login-button"]').click();
    await expect(tim).toHaveURL(/login/);

    await petra.goto('/verwaltung/schiesskladde');
    await petra.locator('#member').selectOption({ label: 'Tim Jungschütze' });
    await petra.getByRole('button', { name: 'Anzeigen' }).click();
    const download = petra.waitForEvent('download');
    await petra.getByRole('link', { name: /Auszug Tim Jungschütze/ }).click();
    expect((await download).suggestedFilename()).toMatch(
        /^Schiesskladde-tim-jungschutze-/,
    );
});

test('CSV-Import mit Vorschau, auch für Mitglieder ohne E-Mail', async ({
    browser,
}) => {
    const petra = await as(browser, 'petra.vorstand@demo.test');
    await petra.goto('/verwaltung/mitglieder/import');
    await petra.locator('#file').setInputFiles({
        name: 'mitglieder.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from(
            'Vorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt\n' +
                'Olga;Ohnemail;;5001;01.04.2010\n' +
                'Mia;Mitmail;mia.mitmail@demo.test;5002;\n' +
                'Niemand;;;;\n',
        ),
    });
    await petra.getByRole('button', { name: 'Datei prüfen' }).click();
    await expect(petra.getByText('1 Zeile wird übersprungen')).toBeVisible();
    await expect(
        petra.getByRole('row', { name: /Olga Ohnemail/ }),
    ).toContainText('ohne Konto');
    await petra.getByRole('button', { name: /übernehmen/i }).click();
    await expect(
        petra.getByText('2 neu angelegt, 0 aktualisiert.'),
    ).toBeVisible();
    await expect(
        petra.getByRole('link', { name: 'Olga Ohnemail' }),
    ).toBeVisible();
});
