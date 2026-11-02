<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aufsichtsregister nach § 10 Abs. 3 AWaffV. Es wird nur angehängt; es gilt
     * die jüngste Zeile je Person. users.supervisor_until/-scope sind nur noch
     * ein abgeleiteter Zwischenspeicher dieser jüngsten Zeile.
     */
    public function up(): void
    {
        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('scope');
            $table->date('valid_until')->nullable();
            $table->date('checked_on');
            $table->foreignId('checked_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('supervisor_scope')->nullable()->after('supervisor_until');
        });

        // Bisherige Angaben übernehmen. Ob die Prüfung damals erlaubnispflichtige
        // Waffen umfasste, ist nicht bekannt; übernommen wird der weitere Umfang
        // mit Vermerk, der Vorstand prüft und trägt bei Bedarf neu ein.
        $now = now();
        foreach (DB::table('users')->whereNotNull('supervisor_until')->get(['id', 'supervisor_until']) as $user) {
            DB::table('qualifications')->insert([
                'user_id' => $user->id,
                'scope' => 'licensed',
                'valid_until' => $user->supervisor_until,
                'checked_on' => $now->toDateString(),
                'checked_by_id' => null,
                'note' => 'Übernommen aus „Aufsicht gültig bis“ – Umfang bitte prüfen',
                'created_at' => $now,
            ]);
            DB::table('users')->where('id', $user->id)->update(['supervisor_scope' => 'licensed']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('supervisor_scope');
        });

        Schema::dropIfExists('qualifications');
    }
};
