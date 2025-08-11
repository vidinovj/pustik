<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\KebijakanTikNonKemlu;
use App\Models\LegalDocument;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate data from kebijakan_tik_non_kemlu to legal_documents
        foreach (KebijakanTikNonKemlu::all() as $oldDoc) {
            $issueDate = null;
            if ($oldDoc->tahun_penerbitan) {
                // Create a dummy date for the year
                $issueDate = $oldDoc->tahun_penerbitan . '-01-01';
            }

            LegalDocument::create([
                'title' => $oldDoc->perihal,
                'document_type' => 'Kebijakan TIK Non Kemlu',
                'document_number' => $oldDoc->nomor_kebijakan,
                'issue_date' => $issueDate,
                'source_url' => $oldDoc->tautan,
                'metadata' => [
                    'original_jenis_kebijakan' => $oldDoc->jenis_kebijakan,
                    'original_tahun_penerbitan' => $oldDoc->tahun_penerbitan,
                    'agency' => $oldDoc->instansi,
                    'source_site' => 'Manual Entry - Kebijakan TIK Non Kemlu',
                ],
                'full_text' => $oldDoc->perihal,
                'checksum' => md5($oldDoc->perihal . $oldDoc->nomor_kebijakan . $oldDoc->tahun_penerbitan . $oldDoc->instansi),
                'status' => 'active',
            ]);
        }

        // Drop the old table after migration
        Schema::dropIfExists('kebijakan_tik_non_kemlu');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the table if needed for rollback, but data won't be restored
        Schema::create('kebijakan_tik_non_kemlu', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_kebijakan');
            $table->string('nomor_kebijakan');
            $table->year('tahun_penerbitan');
            $table->text('perihal');
            $table->string('instansi');
            $table->string('tautan')->nullable();
            $table->timestamps();
        });
    }
};