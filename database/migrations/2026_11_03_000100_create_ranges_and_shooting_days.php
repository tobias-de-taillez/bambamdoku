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
        // Ein Stand: z. B. "25 m Kurzwaffe" mit 6 Bahnen.
        Schema::create('ranges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('lanes');
            $table->json('weapon_types');
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('shooting_days', function (Blueprint $table) {
            $table->id();
            $table->uuid('series')->nullable()->index();
            $table->date('date')->index();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('restricted_to_groups')->default(false);
            $table->boolean('guests_allowed')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('range_shooting_day', function (Blueprint $table) {
            $table->foreignId('shooting_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('range_id')->constrained()->cascadeOnDelete();
            $table->primary(['shooting_day_id', 'range_id']);
        });

        Schema::create('group_shooting_day', function (Blueprint $table) {
            $table->foreignId('shooting_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->primary(['shooting_day_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_shooting_day');
        Schema::dropIfExists('range_shooting_day');
        Schema::dropIfExists('shooting_days');
        Schema::dropIfExists('ranges');
    }
};
