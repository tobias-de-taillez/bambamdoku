<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Registrierung ist aus, deshalb legt der Seeder den ersten Admin an.
     * Zugangsdaten kommen aus ADMIN_EMAIL / ADMIN_PASSWORD in der .env.
     */
    public function run(): void
    {
        $email = config('app.admin.email');
        $password = config('app.admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL/ADMIN_PASSWORD nicht gesetzt, kein Admin angelegt.');

            return;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => config('app.admin.name'),
            'password' => $password,
            'role' => Role::Admin,
            'email_verified_at' => now(),
        ]);
    }
}
