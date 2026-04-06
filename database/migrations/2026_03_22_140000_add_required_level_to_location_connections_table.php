<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_connections', function (Blueprint $table): void {
            $table->unsignedSmallInteger('required_level')->default(1)->after('stamina_cost');
        });
    }

    public function down(): void
    {
        Schema::table('location_connections', function (Blueprint $table): void {
            $table->dropColumn('required_level');
        });
    }
};
