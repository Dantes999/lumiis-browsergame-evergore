<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_skills', function (Blueprint $table): void {
            $table->unsignedSmallInteger('mastery_progress')->default(0)->after('mastery_rank');
            $table->unsignedSmallInteger('grand_mastery_rank')->default(0)->after('mastery_progress');
            $table->timestamp('perfect_mastered_at')->nullable()->after('book_available_at');
        });
    }

    public function down(): void
    {
        Schema::table('character_skills', function (Blueprint $table): void {
            $table->dropColumn([
                'mastery_progress',
                'grand_mastery_rank',
                'perfect_mastered_at',
            ]);
        });
    }
};
