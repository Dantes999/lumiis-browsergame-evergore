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
        Schema::create('game_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('name', 20)->unique();
            $table->string('faction', 20)->index();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('experience')->default(0);
            $table->unsignedBigInteger('yang')->default(0);
            $table->unsignedSmallInteger('health')->default(100);
            $table->unsignedSmallInteger('max_health')->default(100);
            $table->unsignedSmallInteger('stamina')->default(12);
            $table->unsignedSmallInteger('max_stamina')->default(12);
            $table->unsignedSmallInteger('attack')->default(8);
            $table->unsignedSmallInteger('defense')->default(6);
            $table->unsignedSmallInteger('speed')->default(5);
            $table->timestamp('action_available_at')->nullable()->index();
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_characters');
    }
};
