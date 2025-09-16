<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            // Only add columns that don't exist
            if (! Schema::hasColumn('legal_documents', 'tik_relevance_score')) {
                $table->integer('tik_relevance_score')->default(0)->after('metadata');
                $table->index('tik_relevance_score');
            }

            if (! Schema::hasColumn('legal_documents', 'tik_keywords')) {
                $table->json('tik_keywords')->nullable()->after('tik_relevance_score');
            }

            if (! Schema::hasColumn('legal_documents', 'document_category')) {
                $table->string('document_category')->nullable()->after('tik_keywords');
                $table->index('document_category');
            }

            if (! Schema::hasColumn('legal_documents', 'is_tik_related')) {
                $table->boolean('is_tik_related')->default(false)->after('document_category');
                $table->index('is_tik_related');
            }

            // Add new optimized columns
            if (! Schema::hasColumn('legal_documents', 'issue_year')) {
                $table->integer('issue_year')->nullable()->after('document_number');
                $table->index('issue_year');
            }

            if (! Schema::hasColumn('legal_documents', 'document_type_code')) {
                $table->string('document_type_code', 20)->nullable()->after('document_type');
                $table->index('document_type_code');
            }
        });
    }

    public function down()
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            // Drop indexes first, then columns
            $columnsToRemove = [
                'issue_year',
                'document_type_code',
                'tik_relevance_score',
                'tik_keywords',
                'document_category',
                'is_tik_related',
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('legal_documents', $column)) {
                    // Try to drop index (may not exist)
                    try {
                        $table->dropIndex([$column]);
                    } catch (Exception $e) {
                        // Index might not exist, continue
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
