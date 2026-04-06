<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('story_text');
            $table->text('description')->nullable();
            $table->string('feature_unlock_hint')->nullable();
            $table->unsignedTinyInteger('required_level')->default(1);
            $table->string('prerequisite_slug')->nullable();
            $table->string('objective_type');
            $table->unsignedInteger('objective_target');
            $table->string('objective_area_slug')->nullable();
            $table->unsignedInteger('reward_experience')->default(0);
            $table->unsignedInteger('reward_yang')->default(0);
            $table->string('reward_item_slug')->nullable();
            $table->unsignedSmallInteger('reward_item_quantity')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
