<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            // These attributes are the long-term build foundation.
            // They intentionally start at zero so the existing game balance does not jump overnight.
            $table->unsignedSmallInteger('vitality')->default(0)->after('speed');
            $table->unsignedSmallInteger('intelligence')->default(0)->after('vitality');
            $table->unsignedSmallInteger('strength')->default(0)->after('intelligence');
            $table->unsignedSmallInteger('dexterity')->default(0)->after('strength');

            // SP is introduced as a combat resource for skills.
            // The first version still refreshes it between fights, but persisting the columns now
            // keeps the future mana system schema-stable.
            $table->unsignedSmallInteger('sp')->default(24)->after('dexterity');
            $table->unsignedSmallInteger('max_sp')->default(24)->after('sp');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            $table->dropColumn([
                'vitality',
                'intelligence',
                'strength',
                'dexterity',
                'sp',
                'max_sp',
            ]);
        });
    }
};
