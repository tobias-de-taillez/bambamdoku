import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { as, failOnConsoleErrors } from './helpers';

/**
 * Mitgliedsantrag: Vorstand öffnet die Registrierung, jemand beantragt die
 * Mitgliedschaft, der Vorstand nimmt auf, danach klappt die Anmeldung.
 */
test('Konto anlegen → Antrag liegt beim Vorstand → Aufnahme → Anmeldung', async ({
    browser,
    page,
}) => {
    const errors = failOnConsoleErrors(page);
    const admin = await as(browser, 'petra.vorstand@demo.test');

    // Anfangs aus: kein Link auf der Anmeldeseite.
    await page.goto('/login');
    await expect(page.getByText('Mitgliedschaft beantragen')).toHaveCount(0);

    await admin.goto('/verwaltung/einstellungen');
    await admin
        .getByRole('checkbox', {
            name: 'Mitgliedschaft online beantragen lassen',
        })
        .click();
    await admin.getByRole('button', { name: 'Speichern' }).first().click();
    await expect(admin.getByText('Einstellungen gespeichert.')).toBeVisible();

    await page.goto('/login');
    await page.locator('[data-test="register-link"]').click();
    await expect(page).toHaveURL(/\/register$/);

    const axe = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
        .analyze();
    expect(axe.violations.map((v) => v.id)).toEqual([]);

    await page.getByLabel('Vor- und Nachname').fill('Rita Registriert');
    await page.getByLabel('E-Mail-Adresse').fill('rita.registriert@demo.test');
    await page
        .getByLabel('Passwort', { exact: true })
        .fill('Rita-Passwort-2026');
    await page.getByLabel('Passwort bestätigen').fill('Rita-Passwort-2026');

    // Ohne Haken „volljährig“ kein Antrag.
    await page.locator('[data-test="register-button"]').click();
    await expect(page.getByText('Mitglied werden kann hier nur')).toBeVisible();

    await page.getByRole('checkbox', { name: /volljährig/ }).click();
    await page
        .getByLabel('Passwort', { exact: true })
        .fill('Rita-Passwort-2026');
    await page.getByLabel('Passwort bestätigen').fill('Rita-Passwort-2026');
    await page.locator('[data-test="register-button"]').click();
    await expect(page).toHaveURL(/\/login$/);
    await expect(
        page.getByText('Ihr Antrag liegt beim Vorstand'),
    ).toBeVisible();

    // Anmelden geht noch nicht.
    await page.getByLabel('E-Mail-Adresse').fill('rita.registriert@demo.test');
    await page
        .getByLabel('Passwort', { exact: true })
        .fill('Rita-Passwort-2026');
    await page.locator('[data-test="login-button"]').click();
    await expect(
        page.getByText('Ihr Antrag liegt beim Vorstand'),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/login$/);

    // Vorstand nimmt auf.
    await admin.goto('/verwaltung/mitglieder?status=applicants');
    const row = admin.getByRole('row', { name: /Rita Registriert/ });
    await row.getByRole('button', { name: 'Aufnehmen' }).click();
    await admin.getByLabel('Mitgliedsnummer').fill('4711');
    await admin.locator('[data-test="admit-submit"]').click();
    await expect(admin.getByText('Bitte bestätigen Sie')).toBeVisible();
    await admin
        .getByRole('checkbox', { name: /Identität und Volljährigkeit geprüft/ })
        .click();
    await admin.locator('[data-test="admit-submit"]').click();
    await expect(
        admin.getByText('Rita Registriert ist aufgenommen'),
    ).toBeVisible();

    // Jetzt klappt die Anmeldung, die eigene Kladde ist erreichbar.
    await page.getByLabel('E-Mail-Adresse').fill('rita.registriert@demo.test');
    await page
        .getByLabel('Passwort', { exact: true })
        .fill('Rita-Passwort-2026');
    await page.locator('[data-test="login-button"]').click();
    await expect(page).toHaveURL(/dashboard/);
    await page.goto('/schiesskladde');
    await expect(page).toHaveURL(/schiesskladde/);

    // Zurück auf aus, damit andere Specs den Ausgangszustand sehen.
    await admin.goto('/verwaltung/einstellungen');
    await admin
        .getByRole('checkbox', {
            name: 'Mitgliedschaft online beantragen lassen',
        })
        .click();
    await admin.getByRole('button', { name: 'Speichern' }).first().click();
    await expect(admin.getByText('Einstellungen gespeichert.')).toBeVisible();

    expect(errors).toEqual([]);
});
