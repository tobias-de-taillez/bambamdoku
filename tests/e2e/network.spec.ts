import { expect, test } from '@playwright/test';
import { as, completeEntry, startSession } from './helpers';

/** Schlechtes Netz am Stand: verständliche Meldung statt stummem Fehlschlag. */

test('Ohne Netz bleibt nichts hängen, erneutes Tippen trägt genau einmal ein', async ({
    browser,
}) => {
    const helga = await as(browser, 'helga.schiesswart@demo.test');
    await helga.goto('/stand');
    await startSession(helga);
    await helga.getByRole('button', { name: 'Eintragen' }).first().click();
    await helga.getByLabel('Mitglied suchen').fill('Lena');
    await helga
        .locator('form')
        .getByRole('button', { name: /Lena Langwaffe/ })
        .click();
    const discipline = helga.locator('#discipline');
    if (
        (await discipline.count()) > 0 &&
        (await discipline.inputValue()) === ''
    ) {
        await discipline.selectOption({ index: 1 });
    }
    await completeEntry(helga);

    await helga.context().setOffline(true);
    await helga
        .locator('form')
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();
    await expect(
        helga.getByText('Keine Verbindung – nichts gespeichert.'),
    ).toBeVisible();

    await helga.context().setOffline(false);
    await helga
        .locator('form')
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();
    await expect(
        helga.getByRole('status').filter({ hasText: 'erfasst um' }),
    ).toBeVisible();
    await expect(
        helga
            .locator('section[aria-labelledby="participants"] > ul > li')
            .filter({ hasText: 'Lena Langwaffe' })
            .locator('ul > li'),
    ).toHaveCount(1);
});

test('Abgelaufene Sitzung: Hinweis und neu laden statt Fehlerseite', async ({
    browser,
}) => {
    const erika = await as(browser, 'erika.musterfrau@demo.test');
    await erika.goto('/buchungen');
    // So antwortet Laravel, wenn das CSRF-Token der offenen Seite abgelaufen ist.
    await erika.route('**/buchungen/**', (route) =>
        route.request().method() === 'DELETE'
            ? route.fulfill({
                  status: 419,
                  contentType: 'text/html',
                  body: '<h1>Page Expired</h1>',
              })
            : route.continue(),
    );
    await erika.getByRole('button', { name: 'Stornieren' }).first().click();
    await expect(erika.getByText('Die Sitzung ist abgelaufen.')).toBeVisible();
    await expect(erika.getByText('Page Expired')).toHaveCount(0);
    await erika.waitForEvent('load');
    await expect(erika).toHaveURL(/buchungen/);
});
