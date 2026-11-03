import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { login } from './helpers';

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
    ],
};

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
}
