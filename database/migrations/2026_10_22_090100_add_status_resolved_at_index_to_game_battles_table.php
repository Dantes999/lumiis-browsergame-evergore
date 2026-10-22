<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_battles', function (Blueprint $table): void {
            // Pruning targets resolved battles by age; the status prefix also keeps
            // ongoing battles cheap to exclude.
            $table->index(['status', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('game_battles', function (Blueprint $table): void {
            $table->dropIndex(['status', 'resolved_at']);
        });
    }
};
