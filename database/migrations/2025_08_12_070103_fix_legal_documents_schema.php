<?php
// database/migrations/xxxx_xx_xx_fix_legal_documents_schema.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            // Fix title column length
            $table->text('title')->change();
            
            // Fix document_number column length  
            $table->text('document_number')->nullable()->change();
            
            // Add TIK-specific fields
            $table->integer('tik_relevance_score')->default(0)->after('metadata');
            $table->json('tik_keywords')->nullable()->after('tik_relevance_score');
            $table->string('document_category')->nullable()->after('tik_keywords');
            $table->boolean('is_tik_related')->default(false)->after('document_category');
            
            // Add indexing for better search
            $table->index('is_tik_related');
            $table->index('document_category');
            $table->index('tik_relevance_score');
        });
    }

    public function down()
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->string('title', 255)->change();
            $table->string('document_number', 255)->nullable()->change();
            
            $table->dropColumn([
                'tik_relevance_score',
                'tik_keywords', 
                'document_category',
                'is_tik_related'
            ]);
        });
    }
};