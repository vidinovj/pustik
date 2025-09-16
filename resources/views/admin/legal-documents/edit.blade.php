@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-white">Ubah Dokumen</h1>
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
        <form action="{{ route('admin.legal-documents.update', $document) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="document_source_id" class="form-label fw-medium text-secondary">Sumber Dokumen:</label>
                    <select name="document_source_id" id="document_source_id" class="form-select" required>
                        <option value="">-- Pilih Sumber --</option>
                        @foreach($documentSources as $source)
                            <option value="{{ $source->id }}" {{ old('document_source_id', $document->document_source_id) == $source->id ? 'selected' : '' }}>
                                {{ $source->name }} ({{ ucfirst($source->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="document_file" class="form-label fw-medium text-secondary">Pilih Berkas Baru (Opsional):</label>
                    <input type="file" name="document_file" id="document_file" accept=".pdf,.doc,.docx" class="form-control">
                    <div class="form-text">Ukuran berkas maksimal: 10MB. Format yang didukung: PDF, DOC, DOCX</div>
                    @if($document->file_path)
                        <div class="form-text">Berkas saat ini: <a href="{{ Storage::url($document->file_path) }}" target="_blank">{{ $document->file_name }}</a></div>
                    @endif
                </div>

                <div class="col-md-12 text-center text-secondary">
                    <p class="mb-2">ATAU</p>
                </div>

                <div class="col-md-12">
                    <label for="source_url" class="form-label fw-medium text-secondary">URL Eksternal (Opsional):</label>
                    <input type="url" name="source_url" id="source_url" value="{{ old('source_url', $document->source_url) }}" class="form-control" placeholder="https://example.com/document.pdf">
                </div>

                <div class="col-md-6">
                    <label for="title" class="form-label fw-medium text-secondary">Judul: *</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $document->title) }}" required class="form-control">
                </div>

                <div class="col-md-6">
                    <label for="document_type" class="form-label fw-medium text-secondary">Tipe Dokumen: *</label>
                    <select name="document_type" id="document_type" required class="form-select">
                        <option value="">-- Pilih Tipe --</option>
                        <optgroup label="Dokumen Internal Pusdatin">
                            <option value="Nota Kesepahaman - MOU" {{ old('document_type', $document->document_type) == 'Nota Kesepahaman - MOU' ? 'selected' : '' }}>Nota Kesepahaman - MOU</option>
                            <option value="Nota Kesepahaman - PKS" {{ old('document_type', $document->document_type) == 'Nota Kesepahaman - PKS' ? 'selected' : '' }}>Nota Kesepahaman - PKS</option>
                            <option value="Dokumen Lainnya" {{ old('document_type', $document->document_type) == 'Dokumen Lainnya' ? 'selected' : '' }}>Dokumen Lainnya</option>
                            <option value="MoU" {{ old('document_type', $document->document_type) == 'MoU' ? 'selected' : '' }}>MoU</option>
                            <option value="PKS" {{ old('document_type', $document->document_type) == 'PKS' ? 'selected' : '' }}>PKS</option>
                            <option value="Agreement" {{ old('document_type', $document->document_type) == 'Agreement' ? 'selected' : '' }}>Agreement</option>
                            <option value="Surat Edaran" {{ old('document_type', $document->document_type) == 'Surat Edaran' ? 'selected' : '' }}>Surat Edaran</option>
                            <option value="Pedoman" {{ old('document_type', $document->document_type) == 'Pedoman' ? 'selected' : '' }}>Pedoman</option>
                            <option value="SOP" {{ old('document_type', $document->document_type) == 'SOP' ? 'selected' : '' }}>Standard Operating Procedure</option>
                        </optgroup>
                        <optgroup label="Dokumen Kebijakan Eksternal">
                            <option value="Undang-Undang" {{ old('document_type', $document->document_type) == 'Undang-Undang' ? 'selected' : '' }}>Undang-Undang</option>
                            <option value="Peraturan Pemerintah" {{ old('document_type', $document->document_type) == 'Peraturan Pemerintah' ? 'selected' : '' }}>Peraturan Pemerintah</option>
                            <option value="Peraturan Presiden" {{ old('document_type', $document->document_type) == 'Peraturan Presiden' ? 'selected' : '' }}>Peraturan Presiden</option>
                            <option value="Peraturan Menteri" {{ old('document_type', $document->document_type) == 'Peraturan Menteri' ? 'selected' : '' }}>Peraturan Menteri</option>
                        </optgroup>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="document_number" class="form-label fw-medium text-secondary">Nomor Dokumen:</label>
                    <input type="text" name="document_number" id="document_number" value="{{ old('document_number', $document->document_number) }}" class="form-control">
                </div>

                <div class="col-md-6">
                    <label for="issue_year" class="form-label fw-medium text-secondary">Tahun Terbit:</label>
                    <input type="number" name="issue_year" id="issue_year" value="{{ old('issue_year', $document->issue_year) }}" min="1900" max="{{ date('Y') + 1 }}" class="form-control">
                </div>

                <div class="col-md-6">
                    <label for="status" class="form-label fw-medium text-secondary">Status:</label>
                    <select name="status" id="status" class="form-select">
                        <option value="draft" {{ old('status', $document->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="active" {{ old('status', $document->status) == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $document->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="pending" {{ old('status', $document->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="tanggal_berakhir" class="form-label fw-medium text-secondary">Tanggal Berakhir (Opsional):</label>
                    <input type="date" name="metadata[tanggal_berakhir]" id="tanggal_berakhir" value="{{ old('metadata.tanggal_berakhir', $document->metadata['tanggal_berakhir'] ?? '') }}" class="form-control">
                </div>

                <div class="col-md-12">
                    <label for="full_text" class="form-label fw-medium text-secondary">Ringkasan/Catatan Dokumen (Opsional):</label>
                    <textarea name="full_text" id="full_text" rows="4" class="form-control" placeholder="Masukkan ringkasan, poin-poin penting, atau catatan...">{{ old('full_text', $document->full_text) }}</textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Perbarui Dokumen</button>
                    <a href="{{ route('admin.legal-documents.index') }}" class="btn btn-secondary">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection