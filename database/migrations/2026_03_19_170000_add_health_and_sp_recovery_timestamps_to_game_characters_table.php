<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            // Health and SP now regenerate passively just like stamina.
            // We persist independent clocks so each resource can have its own pacing.
            $table->timestamp('health_updated_at')->nullable()->after('stamina_updated_at');
            $table->timestamp('sp_updated_at')->nullable()->after('health_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table): void {
            $table->dropColumn([
                'health_updated_at',
                'sp_updated_at',
            ]);
        });
    }
};
