<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_area_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_area_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('pressure')->default(0);
            $table->unsignedInteger('monster_fights')->default(0);
            $table->unsignedInteger('lumiis_searches')->default(0);
            $table->unsignedInteger('boss_fights')->default(0);
            $table->timestamp('last_boss_at')->nullable();
            $table->timestamps();

            $table->unique(['game_character_id', 'location_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_area_progress');
    }
};
