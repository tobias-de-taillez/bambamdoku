import { defineConfig, devices } from '@playwright/test';

/**
 * Durchgehende Browser-Tests der wichtigsten Abläufe gegen den Demo-Verein.
 * Lokal: `npm run build && npm run test:e2e`
 */
const port = 8363;

export default defineConfig({
    testDir: './tests/screenshots',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        locale: 'de-DE',
        timezoneId: 'Europe/Berlin',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${port}`,
        url: `http://127.0.0.1:${port}/up`,
        reuseExistingServer: false,
        env: {
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: 'database/e2e.sqlite',
            APP_URL: `http://127.0.0.1:${port}`,
            MAIL_MAILER: 'array',
        },
    },
});
