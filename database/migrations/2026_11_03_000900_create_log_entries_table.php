<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die Schießkladde des Vereins (§ 15 Abs. 1 Nr. 7 b WaffG). In sie wird nur geschrieben:
     * Korrekturen sind Stornos mit Grund, gelöscht wird nichts.
     */
    public function up(): void
    {
        Schema::create('log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('guest_name')->nullable();
            $table->date('shot_on')->index();
            $table->string('weapon_class');
            $table->string('weapon_owner')->nullable();
            $table->string('discipline')->nullable();
            $table->foreignId('recorded_by_id')->constrained('users')->restrictOnDelete();
            $table->string('source');
            $table->string('late_reason')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->string('cancel_reason')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // 1 bei gültigen Einträgen, sonst NULL: NULL kollidiert in Unique-Indizes nicht,
            // stornierte Zeilen stören den Doppelschutz also nicht.
            $table->unsignedTinyInteger('active_key')->nullable()->storedAs("case when status = 'active' then 1 end");

            $table->unique(['user_id', 'shot_on', 'weapon_class', 'weapon_owner', 'active_key'], 'log_entries_member_unique');
            $table->unique(['guest_name', 'shot_on', 'active_key'], 'log_entries_guest_unique');
        });

        // Bisher bestätigte Besuche (Check-in an der Buchung) übernehmen.
        $classes = ['kurzwaffe' => 'short', 'langwaffe' => 'long', 'luftdruck' => 'exempt', 'bogen' => 'exempt'];
        $seen = [];

        foreach (DB::table('bookings')
            ->join('shooting_days', 'shooting_days.id', '=', 'bookings.shooting_day_id')
            ->where('bookings.status', 'checked_in')
            ->whereNotNull('bookings.checked_in_by_id')
            ->orderBy('bookings.checked_in_at')
            ->get(['bookings.*', 'shooting_days.date']) as $b) {
            $class = $classes[$b->weapon_type] ?? 'exempt';
            $owner = $b->user_id ? ($b->club_weapon ? 'club' : 'own') : null;
            $key = implode('|', [$b->user_id ?? 'g:'.$b->guest_name, substr($b->date, 0, 10), $b->user_id ? $class : '', $owner]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            DB::table('log_entries')->insert([
                'user_id' => $b->user_id,
                'guest_name' => $b->user_id ? null : $b->guest_name,
                'shot_on' => substr($b->date, 0, 10),
                'weapon_class' => $class,
                'weapon_owner' => $owner,
                'recorded_by_id' => $b->checked_in_by_id,
                'source' => 'stand',
                'booking_id' => $b->id,
                'status' => 'active',
                'created_at' => $b->checked_in_at,
                'updated_at' => $b->checked_in_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('log_entries');
    }
};
