<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Das Kalender-Abo ist stillgelegt (K2). Die Spalte bleibt, die alten Links werden ungültig.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('calendar_token')->update(['calendar_token' => null]);
    }

    /**
     * Gelöschte Tokens lassen sich nicht wiederherstellen.
     */
    public function down(): void
    {
        //
    }
};
