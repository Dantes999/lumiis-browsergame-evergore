<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_skills', function (Blueprint $table): void {
            $table->unsignedSmallInteger('mastery_rank')->default(0)->after('rank');
            $table->timestamp('book_available_at')->nullable()->after('mastery_rank');
        });
    }

    public function down(): void
    {
        Schema::table('character_skills', function (Blueprint $table): void {
            $table->dropColumn(['mastery_rank', 'book_available_at']);
        });
    }
};
