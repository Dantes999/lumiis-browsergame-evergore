<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 60);
            $table->string('name');
            $table->text('description');
            $table->string('area_kind', 20)->index();
            $table->string('service_type', 30)->nullable()->index();
            $table->decimal('hotspot_x', 5, 2)->default(50);
            $table->decimal('hotspot_y', 5, 2)->default(50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('recommended_level')->nullable();
            $table->string('monster_name')->nullable();
            $table->string('lumiis_name')->nullable();
            $table->string('boss_name')->nullable();
            $table->unsignedSmallInteger('monster_cooldown_seconds')->default(35);
            $table->unsignedSmallInteger('lumiis_cooldown_seconds')->default(80);
            $table->unsignedSmallInteger('boss_cooldown_seconds')->default(140);
            $table->unsignedSmallInteger('monster_exp_min')->default(0);
            $table->unsignedSmallInteger('monster_exp_max')->default(0);
            $table->unsignedSmallInteger('monster_yang_min')->default(0);
            $table->unsignedSmallInteger('monster_yang_max')->default(0);
            $table->unsignedSmallInteger('monster_damage_min')->default(0);
            $table->unsignedSmallInteger('monster_damage_max')->default(0);
            $table->unsignedSmallInteger('lumiis_exp_min')->default(0);
            $table->unsignedSmallInteger('lumiis_exp_max')->default(0);
            $table->unsignedSmallInteger('lumiis_yang_min')->default(0);
            $table->unsignedSmallInteger('lumiis_yang_max')->default(0);
            $table->unsignedSmallInteger('lumiis_damage_min')->default(0);
            $table->unsignedSmallInteger('lumiis_damage_max')->default(0);
            $table->unsignedSmallInteger('boss_exp_min')->default(0);
            $table->unsignedSmallInteger('boss_exp_max')->default(0);
            $table->unsignedSmallInteger('boss_yang_min')->default(0);
            $table->unsignedSmallInteger('boss_yang_max')->default(0);
            $table->unsignedSmallInteger('boss_damage_min')->default(0);
            $table->unsignedSmallInteger('boss_damage_max')->default(0);
            $table->unsignedSmallInteger('monster_pressure_gain')->default(5);
            $table->unsignedSmallInteger('lumiis_pressure_gain')->default(12);
            $table->unsignedSmallInteger('boss_pressure_threshold')->default(100);
            $table->unsignedSmallInteger('boss_base_chance')->default(200);
            $table->unsignedSmallInteger('boss_pressure_chance_per_point')->default(35);
            $table->json('monster_loot_table')->nullable();
            $table->json('lumiis_loot_table')->nullable();
            $table->json('boss_loot_table')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_areas');
    }
};
