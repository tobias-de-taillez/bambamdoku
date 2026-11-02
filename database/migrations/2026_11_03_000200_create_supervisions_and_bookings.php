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
        // Eingeteilte Standaufsicht (§ 27 WaffG). range_id null = ganze Anlage.
        Schema::create('supervisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('range_id')->nullable()->constrained()->nullOnDelete();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('range_id')->constrained()->cascadeOnDelete();
            // Mitglied oder Gast: Gäste haben keinen Zugang, nur einen Namen und ein einladendes Mitglied.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('weapon_type');
            $table->boolean('club_weapon')->default(false);
            $table->string('status')->default('booked')->index();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('booked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shooting_day_id', 'range_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('supervisions');
    }
};
