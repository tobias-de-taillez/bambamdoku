import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { frischerVerein, VORLAGE } from './lauf';

/** Demo-Verein einmal je Lauf in die Vorlage seeden; die Geschichten kopieren sie. */
export default function globalSetup(): void {
    writeFileSync(VORLAGE, '');
    execSync('php artisan migrate:fresh --force --seed --seeder=DemoSeeder', {
        stdio: 'pipe',
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: VORLAGE,
        },
    });
    frischerVerein();
}
