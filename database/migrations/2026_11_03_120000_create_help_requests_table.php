<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Übersicht der Hilfe-Meldungen („Report Bug / Request Feature“). Die Meldung selbst liegt als Ordner unter
     * storage/app/private/hilfe/…; diese Tabelle ist nur der Index für die Verwaltung.
     */
    public function up(): void
    {
        Schema::create('help_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('url');
            $table->string('component')->nullable();
            $table->text('message');
            $table->string('path');
            $table->string('status', 16)->default('offen')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_requests');
    }
};
