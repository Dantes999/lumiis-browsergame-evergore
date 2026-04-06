<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            // Later Biologist stages now grant permanent combat and farming modifiers that should
            // stack with equipment instead of being stored as transient effects elsewhere.
            $table->unsignedSmallInteger('magic_attack')->default(0)->after('speed');
            $table->unsignedSmallInteger('crit_chance')->default(0)->after('magic_attack');
            $table->unsignedSmallInteger('evade_chance')->default(0)->after('crit_chance');
            $table->unsignedSmallInteger('monster_damage')->default(0)->after('evade_chance');
            $table->unsignedSmallInteger('lumiis_damage')->default(0)->after('monster_damage');
            $table->unsignedSmallInteger('experience_rate')->default(0)->after('lumiis_damage');
            $table->unsignedSmallInteger('yang_rate')->default(0)->after('experience_rate');
            $table->unsignedSmallInteger('loot_rate')->default(0)->after('yang_rate');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            $table->dropColumn([
                'magic_attack',
                'crit_chance',
                'evade_chance',
                'monster_damage',
                'lumiis_damage',
                'experience_rate',
                'yang_rate',
                'loot_rate',
            ]);
        });
    }
};
