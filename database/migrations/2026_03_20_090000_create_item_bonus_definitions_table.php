<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_bonus_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('bonus_type', 50)->unique();
            $table->string('label', 80);
            $table->unsignedInteger('minimum_value')->default(0);
            $table->unsignedInteger('maximum_value')->default(0);
            $table->string('suffix', 20)->default('');
            $table->json('allowed_slots');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_bonus_definitions');
    }
};
