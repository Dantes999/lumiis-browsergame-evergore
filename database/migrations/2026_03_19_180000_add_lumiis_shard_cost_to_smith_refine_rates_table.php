<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smith_refine_rates', function (Blueprint $table): void {
            $table->unsignedSmallInteger('lumiis_shard_cost')->default(0)->after('yang_cost');
        });

        DB::table('smith_refine_rates')
            ->whereIn('upgrade_level', [3, 4, 5])
            ->update(['lumiis_shard_cost' => 1]);

        DB::table('smith_refine_rates')
            ->whereIn('upgrade_level', [6, 7])
            ->update(['lumiis_shard_cost' => 2]);

        DB::table('smith_refine_rates')
            ->where('upgrade_level', 8)
            ->update(['lumiis_shard_cost' => 3]);
    }

    public function down(): void
    {
        Schema::table('smith_refine_rates', function (Blueprint $table): void {
            $table->dropColumn('lumiis_shard_cost');
        });
    }
};
