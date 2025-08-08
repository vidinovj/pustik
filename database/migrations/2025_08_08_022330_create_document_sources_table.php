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
        Schema::create('document_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ['api', 'web_scraping', 'manual']);
            $table->string('base_url')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scraped_at')->nullable(); // Added from model
            $table->unsignedBigInteger('total_documents')->default(0); // Added from model
            $table->text('description')->nullable(); // Added from model
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sources');
    }
};
