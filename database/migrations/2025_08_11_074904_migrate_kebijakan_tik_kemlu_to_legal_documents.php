<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\KebijakanTikKemlu;
use App\Models\LegalDocument;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate data from kebijakan_tik_kemlu to legal_documents
        foreach (KebijakanTikKemlu::all() as $oldDoc) {
            $issueDate = null;
            if ($oldDoc->tahun_penerbitan) {
                // Create a dummy date for the year
                $issueDate = $oldDoc->tahun_penerbitan . '-01-01';
            }

            LegalDocument::create([
                'title' => $oldDoc->perihal_kebijakan,
                'document_type' => 'Kebijakan TIK Kemlu',
                'document_number' => $oldDoc->nomor_kebijakan,
                'issue_date' => $issueDate,
                'source_url' => $oldDoc->tautan,
                'metadata' => [
                    'original_jenis_kebijakan' => $oldDoc->jenis_kebijakan,
                    'original_tahun_penerbitan' => $oldDoc->tahun_penerbitan,
                    'agency' => 'Kementerian Luar Negeri',
                    'source_site' => 'Manual Entry - Kebijakan TIK Kemlu',
                ],
                'full_text' => $oldDoc->perihal_kebijakan,
                'checksum' => md5($oldDoc->perihal_kebijakan . $oldDoc->nomor_kebijakan . $oldDoc->tahun_penerbitan),
                'status' => 'active',
            ]);
        }

        // Drop the old table after migration
        Schema::dropIfExists('kebijakan_tik_kemlu');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the table if needed for rollback, but data won't be restored
        Schema::create('kebijakan_tik_kemlu', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_kebijakan');
            $table->string('nomor_kebijakan');
            $table->year('tahun_penerbitan');
            $table->text('perihal_kebijakan');
            $table->string('tautan')->nullable();
            $table->timestamps();
        });
    }
};