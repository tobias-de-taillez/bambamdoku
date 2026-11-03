import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers';

/** Am Stand wird mit dem Handy gearbeitet: keine Seite darf seitlich scrollen. */
test.use({
    viewport: { width: 375, height: 812 },
    hasTouch: true,
    isMobile: true,
});

async function expectNoSideScroll(page: Page, path: string): Promise<void> {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(
        overflow,
        `${path} scrollt seitlich um ${overflow}px`,
    ).toBeLessThanOrEqual(0);
}

test('Aufsicht: Seiten passen aufs Handy', async ({ page }) => {
    await login(page, 'helga.schiesswart@demo.test');
    for (const path of [
        '/dashboard',
        '/stand',
        '/schiesstage',
        '/buchungen',
        '/schiesskladde',
        '/anleitung',
        '/datenschutz',
        '/settings/profile',
        '/settings/security',
    ]) {
        await expectNoSideScroll(page, path);
    }

    await page.goto('/stand');
    await page.getByRole('button', { name: 'Eintragen' }).first().click();
    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(overflow).toBeLessThanOrEqual(0);
});

test('Vorstand: Verwaltung passt aufs Handy', async ({ page }) => {
    await login(page, 'petra.vorstand@demo.test');
    for (const path of [
        '/verwaltung/schiesskladde',
        '/verwaltung/mitglieder',
        '/verwaltung/schiesstage',
        '/verwaltung/staende',
        '/verwaltung/statistik',
        '/verwaltung/einstellungen',
        '/verwaltung/gruppen',
        '/verwaltung/protokoll',
        '/verwaltung/mitglieder/neu',
        '/verwaltung/mitglieder/import',
        '/verwaltung/schiesstage/neu',
    ]) {
        await expectNoSideScroll(page, path);
    }
});
