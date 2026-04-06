<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('objective_submission_success_rate')
                ->default(100)
                ->after('objective_submission_cooldown_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            $table->dropColumn('objective_submission_success_rate');
        });
    }
};
