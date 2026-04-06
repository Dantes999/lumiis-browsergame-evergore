<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Vendors can now be attached to a specific clickable town hotspot.
            // This lets the map decide which shop inventory is available.
            $table
                ->foreignId('location_area_id')
                ->nullable()
                ->after('location_id')
                ->constrained('location_areas')
                ->nullOnDelete();

            $table->unique('location_area_id');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropUnique(['location_area_id']);
            $table->dropConstrainedForeignId('location_area_id');
        });
    }
};
