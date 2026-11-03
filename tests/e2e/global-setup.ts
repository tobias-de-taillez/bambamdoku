import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';

/** Frische Datenbank mit Demo-Verein für jeden Lauf. */
export default function globalSetup(): void {
    writeFileSync('database/e2e.sqlite', '');
    execSync('php artisan migrate:fresh --force --seed --seeder=DemoSeeder', {
        stdio: 'inherit',
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: 'database/e2e.sqlite',
        },
    });
}
