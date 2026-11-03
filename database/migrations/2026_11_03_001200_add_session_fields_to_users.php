<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Einmalige Bestätigung „volljährig geprüft“ (E12); ohne sie kein Selbsteintrag.
            if (! Schema::hasColumn('users', 'adult_confirmed_at')) {
                $table->timestamp('adult_confirmed_at')->nullable();
            }
            // Hash der Kiosk-PIN (E2); das Mitglied setzt sie selbst, der Vorstand kann sie nur löschen.
            if (! Schema::hasColumn('users', 'kiosk_pin_hash')) {
                $table->string('kiosk_pin_hash')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['adult_confirmed_at', 'kiosk_pin_hash']);
        });
    }
};
