@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-white">Tambah Dokumen Baru</h1>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="form-container p-4 mb-4">
        <form action="{{ route('admin.legal-documents.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="document_source_id" class="form-label fw-medium text-secondary">Sumber Dokumen:</label>
                    <select name="document_source_id" id="document_source_id" class="form-select" required>
                        <option value="">-- Pilih Sumber --</option>
                        @foreach($documentSources as $source)
                            <option value="{{ $source->id }}" {{ old('document_source_id') == $source->id ? 'selected' : '' }}>
                                {{ $source->name }} ({{ ucfirst($source->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="document_file" class="form-label fw-medium text-secondary">Pilih Berkas (PDF, DOC, DOCX):</label>
                    <input type="file" name="document_file" id="document_file" accept=".pdf,.doc,.docx" class="form-control">
                    <div class="form-text">Ukuran berkas maksimal: 10MB. Format yang didukung: PDF, DOC, DOCX</div>
                </div>

                <div class="col-md-12 text-center text-secondary">
                    <p class="mb-2">ATAU</p>
                </div>

                <div class="col-md-12">
                    <label for="source_url" class="form-label fw-medium text-secondary">URL Eksternal (Opsional):</label>
                    <input type="url" name="source_url" id="source_url" value="{{ old('source_url') }}" class="form-control" placeholder="https://example.com/document.pdf">
                </div>

                <div class="col-md-6">
                    <label for="title" class="form-label fw-medium text-secondary">Judul: *</label>
                    <input type="text" name="title" id="title" value="{{ old('title') }}" required class="form-control">
                </div>

                <div class="col-md-6">
                    <label for="document_type" class="form-label fw-medium text-secondary">Tipe Dokumen: *</label>
                    <select name="document_type" id="document_type" required class="form-select">
                        <option value="">-- Pilih Tipe --</option>
                        <optgroup label="Dokumen Internal Pusdatin">
                            <option value="Nota Kesepahaman - MOU" {{ old('document_type') == 'Nota Kesepahaman - MOU' ? 'selected' : '' }}>Nota Kesepahaman - MOU</option>
                            <option value="Nota Kesepahaman - PKS" {{ old('document_type') == 'Nota Kesepahaman - PKS' ? 'selected' : '' }}>Nota Kesepahaman - PKS</option>
                            <option value="Dokumen Lainnya" {{ old('document_type') == 'Dokumen Lainnya' ? 'selected' : '' }}>Dokumen Lainnya</option>
                            <option value="MoU" {{ old('document_type') == 'MoU' ? 'selected' : '' }}>MoU</option>
                            <option value="PKS" {{ old('document_type') == 'PKS' ? 'selected' : '' }}>PKS</option>
                            <option value="Agreement" {{ old('document_type') == 'Agreement' ? 'selected' : '' }}>Agreement</option>
                            <option value="Surat Edaran" {{ old('document_type') == 'Surat Edaran' ? 'selected' : '' }}>Surat Edaran</option>
                            <option value="Pedoman" {{ old('document_type') == 'Pedoman' ? 'selected' : '' }}>Pedoman</option>
                            <option value="SOP" {{ old('document_type') == 'SOP' ? 'selected' : '' }}>Standard Operating Procedure</option>
                        </optgroup>
                        <optgroup label="Dokumen Kebijakan Eksternal">
                            <option value="Undang-Undang" {{ old('document_type') == 'Undang-Undang' ? 'selected' : '' }}>Undang-Undang</option>
                            <option value="Peraturan Pemerintah" {{ old('document_type') == 'Peraturan Pemerintah' ? 'selected' : '' }}>Peraturan Pemerintah</option>
                            <option value="Peraturan Presiden" {{ old('document_type') == 'Peraturan Presiden' ? 'selected' : '' }}>Peraturan Presiden</option>
                            <option value="Peraturan Menteri" {{ old('document_type') == 'Peraturan Menteri' ? 'selected' : '' }}>Peraturan Menteri</option>
                        </optgroup>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="document_number" class="form-label fw-medium text-secondary">Nomor Dokumen:</label>
                    <input type="text" name="document_number" id="document_number" value="{{ old('document_number') }}" class="form-control">
                </div>

                <div class="col-md-6">
                    <label for="issue_year" class="form-label fw-medium text-secondary">Tahun Terbit:</label>
                    <input type="number" name="issue_year" id="issue_year" value="{{ old('issue_year', date('Y')) }}" min="1900" max="{{ date('Y') + 1 }}" class="form-control">
                </div>

                <div class="col-md-6">
                    <label for="status" class="form-label fw-medium text-secondary">Status:</label>
                    <select name="status" id="status" class="form-select">
                        <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="tanggal_berakhir" class="form-label fw-medium text-secondary">Tanggal Berakhir (Opsional):</label>
                    <input type="date" name="metadata[tanggal_berakhir]" id="tanggal_berakhir" value="{{ old('metadata.tanggal_berakhir') }}" class="form-control">
                </div>

                <div class="col-md-6">
                    <label for="satker_kemlu_terkait" class="form-label fw-medium text-secondary">Satker Kemlu Terkait (Opsional):</label>
                    <input type="text" name="metadata[satker_kemlu_terkait]" id="satker_kemlu_terkait" value="{{ old('metadata.satker_kemlu_terkait') }}" class="form-control">
                </div>

                <div class="col-md-6">
                    <label for="kl_external_terkait" class="form-label fw-medium text-secondary">K/L/I External Terkait (Opsional):</label>
                    <input type="text" name="metadata[kl_external_terkait]" id="kl_external_terkait" value="{{ old('metadata.kl_external_terkait') }}" class="form-control">
                </div>

                <div class="col-md-12">
                    <label for="full_text" class="form-label fw-medium text-secondary">Ringkasan/Catatan Dokumen (Opsional):</label>
                    <textarea name="full_text" id="full_text" rows="4" class="form-control" placeholder="Masukkan ringkasan, poin-poin penting, atau catatan...">{{ old('full_text') }}</textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Buat Dokumen</button>
                    <a href="{{ route('admin.legal-documents.index') }}" class="btn btn-secondary">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-populate title from filename
document.getElementById('document_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const titleField = document.getElementById('title');
    
    if (file && !titleField.value) {
        // Remove file extension and clean up filename
        let filename = file.name.replace(/\.[^/.]+$/, "");
        filename = filename.replace(/[-_]/g, ' ');
        filename = filename.replace(/\b\w/g, l => l.toUpperCase());
        titleField.value = filename;
    }
});
</script>
@endsection