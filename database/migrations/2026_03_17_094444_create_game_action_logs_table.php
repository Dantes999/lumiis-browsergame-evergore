<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_character_id')->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->string('action', 24)->index();
            $table->foreignId('source_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('target_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->json('request_payload')->nullable();
            $table->json('outcome')->nullable();
            $table->timestamp('processed_at')->index();
            $table->timestamps();

            $table->unique(['game_character_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_action_logs');
    }
};
