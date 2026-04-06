<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slot', 30);
            $table->timestamps();

            $table->unique(['game_character_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_equipment');
    }
};
