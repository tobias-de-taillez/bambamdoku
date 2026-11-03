import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { login, startSession } from './helpers';

/** Barrierefreiheit (WCAG 2.1 AA) der wichtigsten Seiten, hell und dunkel. */

const pages: Record<string, string[]> = {
    'helga.schiesswart@demo.test': [
        '/dashboard',
        '/stand',
        '/schiesstage',
        '/buchungen',
        '/schiesskladde',
        '/anleitung',
    ],
    'petra.vorstand@demo.test': [
        '/verwaltung/schiesskladde',
        '/verwaltung/mitglieder',
        '/verwaltung/schiesstage',
        '/verwaltung/statistik',
        '/verwaltung/einstellungen',
    ],
};

async function axeViolations(
    page: import('@playwright/test').Page,
): Promise<string[]> {
    // Die Toasts kommen fertig aus svelte-sonner; ihre Listenstruktur prüfen wir nicht mit.
    const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
        .exclude('.toaster')
        .analyze();

    return result.violations.map(
        (v) =>
            `${v.id} (${v.impact}): ${v.nodes
                .map((n) => n.target.join(' '))
                .slice(0, 3)
                .join(' | ')}`,
    );
}

for (const scheme of ['light', 'dark'] as const) {
    test(`Anmeldeseite ohne Barrieren (${scheme})`, async ({ page }) => {
        await page.emulateMedia({ colorScheme: scheme });
        await page.goto('/login');
        const result = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
            .analyze();
        expect(
            result.violations.map(
                (v) =>
                    `${v.id}: ${v.nodes
                        .map((n) => n.target.join(' '))
                        .slice(0, 3)
                        .join(' | ')}`,
            ),
        ).toEqual([]);
    });

    for (const [email, paths] of Object.entries(pages)) {
        test(`${email.split('.')[0]}: Seiten ohne Barrieren (${scheme})`, async ({
            page,
        }) => {
            await page.emulateMedia({ colorScheme: scheme });
            await login(page, email);
            const found: string[] = [];

            for (const path of paths) {
                await page.goto(path);
                await page.waitForLoadState('networkidle');
                const result = await new AxeBuilder({ page })
                    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
                    .analyze();
                found.push(
                    ...result.violations.map(
                        (v) =>
                            `${path} ${v.id} (${v.impact}): ${v.nodes
                                .map((n) => n.target.join(' '))
                                .slice(0, 3)
                                .join(' | ')}`,
                    ),
                );
            }

            expect(found).toEqual([]);
        });
    }

    test(`Stand mit offener Session und Abzeichnen-Dialog ohne Barrieren (${scheme})`, async ({
        page,
    }) => {
        await page.emulateMedia({ colorScheme: scheme });
        await login(page, 'helga.schiesswart@demo.test');
        await page.goto('/stand');
        await startSession(page);

        // Ein Gast als Teilnehmer, damit Teilnehmerliste und Eintrag geprüft werden.
        await page.getByRole('button', { name: 'Eintragen' }).first().click();
        await page.getByRole('tab', { name: 'Gast' }).click();
        await page.getByLabel('Name des Gastes').fill(`Axe Gast ${scheme}`);
        await page
            .getByRole('button', { name: 'erlaubnisfrei', exact: true })
            .click();
        await page.getByText('Gast ist volljährig (Ausweis gesehen)').click();
        await page
            .locator('form')
            .getByRole('button', { name: 'Erfassen', exact: true })
            .click();
        await expect(
            page
                .locator('section[aria-labelledby="participants"] > ul > li')
                .filter({ hasText: `Axe Gast ${scheme}` }),
        ).toHaveCount(1);

        const found = (await axeViolations(page)).map((v) => `Stand: ${v}`);

        await page.getByRole('button', { name: 'Abzeichnen' }).first().click();
        await expect(
            page.getByText(/unter meiner Aufsicht geschossen/),
        ).toBeVisible();
        found.push(
            ...(await axeViolations(page)).map((v) => `Abzeichnen: ${v}`),
        );

        // Aufräumen: abzeichnen, damit die nächste Prüfung eine neue Session startet.
        await page
            .getByRole('button', { name: 'Bestätigen und abzeichnen' })
            .click();
        await expect(
            page.getByRole('heading', { name: 'Heute abgezeichnet' }),
        ).toBeVisible();

        expect(found).toEqual([]);
    });
}
