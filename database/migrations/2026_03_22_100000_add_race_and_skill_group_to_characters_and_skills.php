<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            $table->string('race', 30)->default('warrior')->after('faction');
            $table->string('skill_group', 40)->nullable()->after('race');
            $table->index(['race', 'skill_group']);
        });

        Schema::table('skills', function (Blueprint $table): void {
            $table->string('race', 30)->nullable()->after('faction');
            $table->string('skill_group', 40)->nullable()->after('race');
            $table->index(['race', 'skill_group']);
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table): void {
            $table->dropIndex(['race', 'skill_group']);
            $table->dropColumn(['race', 'skill_group']);
        });

        Schema::table('game_characters', function (Blueprint $table): void {
            $table->dropIndex(['race', 'skill_group']);
            $table->dropColumn(['race', 'skill_group']);
        });
    }
};
