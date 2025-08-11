<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\NotaKesepahaman;
use App\Models\LegalDocument;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate data from nota_kesepahaman to legal_documents
        foreach (NotaKesepahaman::all() as $oldDoc) {
            $statusMap = [
                'Aktif' => 'active',
                'Tidak Aktif' => 'inactive',
                'Dalam Perpanjangan' => 'pending',
            ];

            LegalDocument::create([
                'title' => $oldDoc->perihal_dokumen,
                'document_type' => 'Nota Kesepahaman - ' . $oldDoc->jenis_dokumen,
                'document_number' => null, // No direct mapping for document_number
                'issue_date' => $oldDoc->tanggal_disahkan,
                'source_url' => null, // No direct mapping for source_url
                'metadata' => [
                    'jenis_dokumen' => $oldDoc->jenis_dokumen,
                    'satker_kemlu_terkait' => $oldDoc->satker_kemlu_terkait,
                    'kl_external_terkait' => $oldDoc->kl_external_terkait,
                    'tanggal_berakhir' => $oldDoc->tanggal_berakhir,
                    'keterangan' => $oldDoc->keterangan,
                    'agency' => 'Kementerian Luar Negeri',
                    'source_site' => 'Manual Entry - Nota Kesepahaman',
                ],
                'full_text' => $oldDoc->perihal_dokumen . ' ' . $oldDoc->keterangan,
                'checksum' => md5($oldDoc->perihal_dokumen . $oldDoc->tanggal_disahkan . $oldDoc->jenis_dokumen),
                'status' => $statusMap[$oldDoc->status] ?? 'draft',
            ]);
        }

        // Drop the old table after migration
        Schema::dropIfExists('nota_kesepahaman');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the table if needed for rollback, but data won't be restored
        Schema::create('nota_kesepahaman', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis_dokumen', ['MOU', 'PKS']);
            $table->text('perihal_dokumen');
            $table->string('satker_kemlu_terkait');
            $table->string('kl_external_terkait');
            $table->date('tanggal_disahkan');
            $table->date('tanggal_berakhir');
            $table->enum('status', ['Aktif', 'Tidak Aktif', 'Dalam Perpanjangan']);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }
};