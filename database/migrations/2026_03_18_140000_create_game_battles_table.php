<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_battles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 20);
            $table->string('requested_encounter', 20)->nullable();
            $table->string('encounter_type', 20);
            $table->string('encounter_name');
            $table->string('status', 20)->default('ongoing');
            $table->unsignedInteger('round')->default(1);
            $table->json('state');
            $table->json('rewards')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['game_character_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_battles');
    }
};
