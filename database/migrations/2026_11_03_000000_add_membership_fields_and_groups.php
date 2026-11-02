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
            $table->boolean('active')->default(true)->after('role');
            $table->string('member_number')->nullable()->unique()->after('active');
            // Bis wann der Benutzer als verantwortliche Aufsichtsperson (§ 10 AWaffV) eingeteilt werden darf.
            $table->date('supervisor_until')->nullable()->after('member_number');
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['group_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['member_number']);
            $table->dropColumn(['active', 'member_number', 'supervisor_until']);
        });
    }
};
