<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('items') || ! Schema::hasColumn('items', 'travel_speed_percent_bonus')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE items MODIFY travel_speed_percent_bonus SMALLINT UNSIGNED NOT NULL DEFAULT 0');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE items ALTER COLUMN travel_speed_percent_bonus TYPE SMALLINT');
            DB::statement('ALTER TABLE items ALTER COLUMN travel_speed_percent_bonus SET DEFAULT 0');
            DB::statement('ALTER TABLE items ADD CONSTRAINT items_travel_speed_percent_bonus_nonnegative CHECK (travel_speed_percent_bonus >= 0)');
        }
    }

    public function down(): void {}
};
