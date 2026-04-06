<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            // Blue potions now refill SP/mana instead of stamina.
            // Stamina stays a pacing resource that only comes back through time and rest.
            $table->unsignedSmallInteger('sp_restore')->default(0)->after('stamina_restore');
        });

        DB::table('items')
            ->where('slug', 'small-blue-potion')
            ->update([
                'description' => 'A focused tonic that restores 4 SP for skill use.',
                'stamina_restore' => 0,
                'sp_restore' => 4,
            ]);

        DB::table('items')
            ->where('slug', 'medium-blue-potion')
            ->update([
                'description' => 'A concentrated tonic that restores 8 SP.',
                'stamina_restore' => 0,
                'sp_restore' => 8,
            ]);

        DB::table('items')
            ->where('slug', 'large-blue-potion')
            ->update([
                'description' => 'A rare reserve flask that restores 14 SP.',
                'stamina_restore' => 0,
                'sp_restore' => 14,
            ]);
    }

    public function down(): void
    {
        DB::table('items')
            ->where('slug', 'small-blue-potion')
            ->update([
                'description' => 'A focused tonic that restores 4 stamina for the next action cycle.',
                'stamina_restore' => 4,
                'sp_restore' => 0,
            ]);

        DB::table('items')
            ->where('slug', 'medium-blue-potion')
            ->update([
                'description' => 'A concentrated tonic that restores 8 stamina.',
                'stamina_restore' => 8,
                'sp_restore' => 0,
            ]);

        DB::table('items')
            ->where('slug', 'large-blue-potion')
            ->update([
                'description' => 'A rare reserve flask that restores 14 stamina.',
                'stamina_restore' => 14,
                'sp_restore' => 0,
            ]);

        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn('sp_restore');
        });
    }
};
