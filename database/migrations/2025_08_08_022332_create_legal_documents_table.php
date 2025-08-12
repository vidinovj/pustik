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
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable(); // Added from model
            $table->date('issue_date')->nullable();
            $table->string('source_url')->nullable();
            $table->json('metadata')->nullable();
            $table->longText('full_text')->nullable();
            $table->foreignUuid('document_source_id')->nullable()->constrained('document_sources')->onDelete('set null'); // Added from model
            $table->string('status')->default('draft'); // Added from model, with default
            $table->string('checksum')->unique()->nullable(); // Added from model
            $table->timestamps();

            // Add indexes for search optimization
            $table->index('document_type');
            $table->index('issue_date');
            $table->fullText('title');
            $table->fullText('full_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
