<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_character_id')->constrained('game_characters')->cascadeOnDelete();
            $table->foreignId('recipient_character_id')->nullable()->constrained('game_characters')->cascadeOnDelete();
            $table->string('channel', 20)->index();
            $table->string('body', 500);
            $table->timestamps();

            $table->index(['channel', 'created_at']);
            $table->index(['recipient_character_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_messages');
    }
};
