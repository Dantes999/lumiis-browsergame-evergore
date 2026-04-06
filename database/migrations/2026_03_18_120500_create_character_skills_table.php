<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank')->default(1);
            $table->timestamps();

            $table->unique(['game_character_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_skills');
    }
};
