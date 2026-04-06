<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add item turn-in and permanent reward bonus fields for Biologist-style quests.
     */
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            $table->string('objective_item_slug')->nullable()->after('objective_area_slug');
            $table->unsignedSmallInteger('objective_item_quantity')->default(1)->after('objective_item_slug');
            $table->unsignedInteger('objective_submission_cooldown_minutes')->default(0)->after('objective_item_quantity');
            $table->string('reward_bonus_type')->nullable()->after('reward_item_quantity');
            $table->unsignedInteger('reward_bonus_value')->default(0)->after('reward_bonus_type');
        });

        Schema::table('character_quests', function (Blueprint $table): void {
            $table->timestamp('next_submission_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('character_quests', function (Blueprint $table): void {
            $table->dropColumn('next_submission_at');
        });

        Schema::table('quests', function (Blueprint $table): void {
            $table->dropColumn([
                'objective_item_slug',
                'objective_item_quantity',
                'objective_submission_cooldown_minutes',
                'reward_bonus_type',
                'reward_bonus_value',
            ]);
        });
    }
};
