<?php
// database/migrations/2025_08_26_cleanup_redundant_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            // Add new optimized columns
            $table->integer('issue_year')->nullable()->after('document_number');
            $table->string('document_type_code', 20)->nullable()->after('document_type');
            
            // Add indexes for the new columns
            $table->index('issue_year');
            $table->index('document_type_code');
        });
    }

    public function down()
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropIndex(['issue_year']);
            $table->dropIndex(['document_type_code']);
            $table->dropColumn(['issue_year', 'document_type_code']);
        });
    }
};