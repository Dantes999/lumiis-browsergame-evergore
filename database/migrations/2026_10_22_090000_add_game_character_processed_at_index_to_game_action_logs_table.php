<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_action_logs', function (Blueprint $table): void {
            // The dashboard reads the newest eight logs for one character. The index
            // keeps that query from sorting the character's whole history.
            $table->index(['game_character_id', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('game_action_logs', function (Blueprint $table): void {
            $table->dropIndex(['game_character_id', 'processed_at']);
        });
    }
};
