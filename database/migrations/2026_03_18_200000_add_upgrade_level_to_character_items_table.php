<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_items', function (Blueprint $table) {
            // Upgrade level belongs to the owned inventory row, not the static item definition.
            // That lets two copies of the same sword exist at different enhancement stages.
            $table->unsignedTinyInteger('upgrade_level')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('character_items', function (Blueprint $table) {
            $table->dropColumn('upgrade_level');
        });
    }
};
