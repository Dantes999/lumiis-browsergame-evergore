<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_area_monsters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('weight')->default(100);
            $table->unsignedSmallInteger('minimum_character_level')->default(1);
            $table->unsignedSmallInteger('maximum_character_level')->nullable();
            $table->unsignedTinyInteger('pack_size')->default(3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['location_area_id', 'monster_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_area_monsters');
    }
};
