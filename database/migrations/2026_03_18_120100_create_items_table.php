<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('item_type', 30)->index();
            $table->string('equipment_slot', 30)->nullable()->index();
            $table->text('description');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('sell_price')->default(0);
            $table->unsignedSmallInteger('required_level')->default(1);
            $table->boolean('stackable')->default(false);
            $table->unsignedSmallInteger('max_stack')->default(1);
            $table->integer('attack_bonus')->default(0);
            $table->integer('defense_bonus')->default(0);
            $table->integer('health_bonus')->default(0);
            $table->integer('stamina_bonus')->default(0);
            $table->integer('speed_bonus')->default(0);
            $table->unsignedSmallInteger('travel_speed_percent_bonus')->default(0);
            $table->unsignedSmallInteger('heal_amount')->default(0);
            $table->unsignedSmallInteger('stamina_restore')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
