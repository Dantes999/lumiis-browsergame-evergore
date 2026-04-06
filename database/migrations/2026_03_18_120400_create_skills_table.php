<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('faction', 20)->nullable()->index();
            $table->string('name');
            $table->text('description');
            $table->unsignedSmallInteger('required_level')->default(5);
            $table->unsignedSmallInteger('max_rank')->default(10);
            $table->string('effect_type', 30)->index();
            $table->integer('effect_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
