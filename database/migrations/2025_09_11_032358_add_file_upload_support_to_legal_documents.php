<?php

// Create: php artisan make:migration add_file_upload_support_to_legal_documents

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            // File storage fields for internal documents
            $table->string('file_path')->nullable()->after('pdf_url');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_type')->nullable()->after('file_name'); // pdf, doc, docx
            $table->bigInteger('file_size')->nullable()->after('file_type'); // in bytes

            // Upload metadata
            $table->string('uploaded_by')->nullable()->after('file_size');
            $table->timestamp('uploaded_at')->nullable()->after('uploaded_by');

            // Index for searches
            $table->index(['file_type', 'uploaded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropIndex(['file_type', 'uploaded_at']);
            $table->dropColumn([
                'file_path', 'file_name', 'file_type', 'file_size',
                'uploaded_by', 'uploaded_at',
            ]);
        });
    }
};
