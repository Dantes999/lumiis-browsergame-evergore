<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_quests', function (Blueprint $table): void {
            $table->unsignedTinyInteger('submission_bonus_percent')
                ->default(0)
                ->after('next_submission_at');
        });
    }

    public function down(): void
    {
        Schema::table('character_quests', function (Blueprint $table): void {
            $table->dropColumn('submission_bonus_percent');
        });
    }
};
