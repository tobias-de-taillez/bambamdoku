import { defineConfig, devices } from '@playwright/test';
import { ARTEFAKTE, BERICHT, DB, PORT } from './tests/rollen/lauf';

/**
 * Rollen-Rundgang: alle Rollen und ihr Zusammenspiel im Browser, mit Fotos als Beleg.
 * Lokal: `npm run build && npm run test:rollen`, Bericht unter test-results/rollen/bericht/index.html
 */
export default defineConfig({
    testDir: './tests/rollen',
    outputDir: ARTEFAKTE,
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 180_000,
    expect: { timeout: 10_000 },
    reporter: [
        [process.env.CI ? 'github' : 'list'],
        ['./tests/rollen/beweisbericht.ts', { ordner: BERICHT }],
    ],
    globalSetup: './tests/rollen/global-setup.ts',
    use: {
        baseURL: `http://127.0.0.1:${PORT}`,
        locale: 'de-DE',
        timezoneId: 'Europe/Berlin',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${PORT}`,
        url: `http://127.0.0.1:${PORT}/up`,
        reuseExistingServer: false,
        env: {
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: DB,
            APP_URL: `http://127.0.0.1:${PORT}`,
            MAIL_MAILER: 'array',
        },
    },
});
