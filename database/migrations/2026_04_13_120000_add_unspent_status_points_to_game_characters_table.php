<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            $table->unsignedSmallInteger('unspent_status_points')
                ->default(0)
                ->after('unspent_skill_points');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            $table->dropColumn('unspent_status_points');
        });
    }
};