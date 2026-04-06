<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            $table->string('game_state')->default('active')->after('faction');
            $table->unsignedTinyInteger('tutorial_step')->nullable()->after('game_state');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            $table->dropColumn(['game_state', 'tutorial_step']);
        });
    }
};
