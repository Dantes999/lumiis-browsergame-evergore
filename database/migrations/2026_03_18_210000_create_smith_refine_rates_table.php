<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smith_refine_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('upgrade_level')->unique();
            $table->unsignedTinyInteger('success_percent');
            $table->unsignedInteger('yang_cost');
            $table->boolean('destroy_on_fail')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smith_refine_rates');
    }
};
