<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // The grid currently uses one fixed column width, so items only need a height.
            // 1 = single slot, 2 = medium vertical item, 3 = tall two-hand style item.
            $table->unsignedSmallInteger('inventory_height')->default(1)->after('max_stack');
            $table->string('icon_path')->nullable()->after('inventory_height');
        });

        Schema::table('character_items', function (Blueprint $table) {
            // Inventory positions are nullable because equipped items live outside the bag grid.
            $table->unsignedSmallInteger('inventory_column')->nullable()->after('upgrade_level');
            $table->unsignedSmallInteger('inventory_row')->nullable()->after('inventory_column');

            $table->index(['game_character_id', 'inventory_row', 'inventory_column'], 'character_items_grid_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('character_items', function (Blueprint $table) {
            $table->dropIndex('character_items_grid_lookup');
            $table->dropColumn(['inventory_column', 'inventory_row']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['inventory_height', 'icon_path']);
        });
    }
};
