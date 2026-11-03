import { expect, test } from '@playwright/test';
import { as } from './helpers';

/**
 * Ein ganzer Schießtag: Vorstand plant heute, teilt die Aufsicht ein,
 * ein Mitglied bucht, die Aufsicht bestätigt am Stand – das landet in der Kladde.
 */

function today(): string {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

test('Schießtag von der Planung bis zum Eintrag in der Kladde', async ({
    browser,
}) => {
    // Gebucht werden kann nur, was heute noch nicht vorbei ist.
    test.skip(
        new Date().getHours() >= 23,
        'Kurz vor Mitternacht ist heute keine Buchung mehr frei.',
    );

    const petra = await as(browser, 'petra.vorstand@demo.test');
    await petra.goto('/verwaltung/schiesstage/neu');
    await petra.locator('#date').fill(today());
    await petra.locator('#starts_at').fill('00:00');
    await petra.locator('#ends_at').fill('23:45');
    await petra.locator('#title').fill('E2E-Tag');
    await petra.getByRole('button', { name: 'Anlegen', exact: true }).click();
    await expect(petra).toHaveURL(/verwaltung\/schiesstage/);

    await petra.goto('/schiesstage');
    await petra.getByRole('link', { name: /E2E-Tag/ }).click();
    await expect(petra).toHaveURL(/schiesstage\/\d+/);
    const dayUrl = petra.url();
    await petra.getByRole('button', { name: 'Aufsicht übernehmen' }).click();
    await petra.locator('#sv-user').selectOption({ label: 'Helga Schießwart' });
    await petra
        .locator('section[aria-labelledby="supervision"]')
        .getByRole('button', { name: 'Eintragen' })
        .click();
    await expect(
        petra.locator('section[aria-labelledby="supervision"]'),
    ).toContainText('Helga Schießwart');

    const erika = await as(browser, 'erika.musterfrau@demo.test');
    await erika.goto(dayUrl);
    await erika.locator('table button:not([disabled])').first().click();
    await erika.getByRole('button', { name: /Uhr buchen/ }).click();
    await expect(
        erika.getByRole('heading', { name: 'Ihre Buchungen an diesem Tag' }),
    ).toBeVisible();

    const helga = await as(browser, 'helga.schiesswart@demo.test');
    await helga.goto('/stand');
    await expect(helga.getByText('E2E-Tag').first()).toBeVisible();
    await helga.getByRole('button', { name: 'Anwesend' }).first().click();

    const entries = helga.locator('section[aria-labelledby="today"] li');
    await expect(entries.filter({ hasText: 'Erika Musterfrau' })).toHaveCount(
        1,
    );
    await expect(entries.filter({ hasText: 'Erika Musterfrau' })).toContainText(
        'Kurzwaffe',
    );
});
