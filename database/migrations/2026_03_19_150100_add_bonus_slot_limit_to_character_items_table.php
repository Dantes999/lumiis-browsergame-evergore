<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_items', function (Blueprint $table): void {
            // Items can currently roll up to four normal bonuses.
            // A future boss-drop item can raise this to five without another schema rewrite.
            $table->unsignedTinyInteger('bonus_slot_limit')->default(4)->after('upgrade_level');
        });
    }

    public function down(): void
    {
        Schema::table('character_items', function (Blueprint $table): void {
            $table->dropColumn('bonus_slot_limit');
        });
    }
};
