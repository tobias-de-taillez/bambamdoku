import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { expect, type Browser, type Page } from '@playwright/test';

export const PASSWORD = 'demo-passwort';
const AUTH_DIR = 'test-results/.auth';

/**
 * Anmelden. Beim ersten Mal je Person über das Formular, danach mit der
 * gespeicherten Sitzung – der Login ist auf 5 Versuche je Minute begrenzt.
 */
export async function login(page: Page, email: string): Promise<void> {
    const file = `${AUTH_DIR}/${email}.json`;

    if (existsSync(file)) {
        await page
            .context()
            .addCookies(JSON.parse(readFileSync(file, 'utf8')).cookies);
        await page.goto('/dashboard');

        if (page.url().includes('/dashboard')) {
            return;
        }
    }

    await page.goto('/login');
    await page.getByLabel('E-Mail-Adresse').fill(email);
    await page.getByLabel('Passwort', { exact: true }).fill(PASSWORD);
    await page.locator('[data-test="login-button"]').click();
    await expect(page).toHaveURL(/dashboard/);

    mkdirSync(AUTH_DIR, { recursive: true });
    writeFileSync(file, JSON.stringify(await page.context().storageState()));
}

/** Eigener Browser-Kontext je Person, damit mehrere gleichzeitig angemeldet sind. */
export async function as(browser: Browser, email: string): Promise<Page> {
    const page = await (await browser.newContext()).newPage();
    await login(page, email);

    return page;
}

export function failOnConsoleErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });

    return errors;
}
