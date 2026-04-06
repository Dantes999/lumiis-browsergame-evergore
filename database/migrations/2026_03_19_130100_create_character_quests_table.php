<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_quests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['game_character_id', 'quest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_quests');
    }
};
