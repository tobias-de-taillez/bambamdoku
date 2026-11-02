<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mitglieder brauchen weder Konto noch E-Mail-Adresse (K4): Die Aufsicht trägt
     * sie per Name oder Mitgliedsnummer ein. Konten haben Admins, Aufsichten und
     * Mitglieder, die ihren Auszug selbst laden wollen.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
