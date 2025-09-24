<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropPrimary('jobs_id_primary');
            $table->dropColumn('id');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropPrimary('failed_jobs_id_primary');
            $table->dropColumn('id');
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropPrimary('jobs_id_primary');
            $table->dropColumn('id');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropPrimary('failed_jobs_id_primary');
            $table->dropColumn('id');
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });
    }
};