<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_item_bonuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot_number');
            $table->string('bonus_type', 50);
            $table->integer('bonus_value');
            $table->timestamps();

            $table->unique(['character_item_id', 'slot_number']);
            $table->index(['bonus_type', 'bonus_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_item_bonuses');
    }
};
