<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monsters', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('level')->index();
            $table->unsignedTinyInteger('rank')->default(1);
            $table->string('race', 40)->default('Tiere');
            $table->string('damage_type', 40)->default('Physisch');
            $table->string('sprite')->nullable();
            $table->unsignedSmallInteger('maximum_health');
            $table->unsignedSmallInteger('minimum_attack');
            $table->unsignedSmallInteger('maximum_attack');
            $table->unsignedSmallInteger('defense')->default(0);
            $table->unsignedInteger('experience_reward');
            $table->unsignedSmallInteger('yang_min')->default(0);
            $table->unsignedSmallInteger('yang_max')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monsters');
    }
};
