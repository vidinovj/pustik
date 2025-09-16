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
        Schema::create('url_monitoring', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('url')->unique();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('status', 50)->default('unknown'); // e.g., 'active', 'broken', 'redirected'
            $table->integer('http_status_code')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('failure_count')->default(0);
            $table->timestamp('last_successful_check_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('last_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('url_monitoring');
    }
};
