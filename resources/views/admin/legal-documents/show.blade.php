@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-white">Detail Dokumen</h1>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ $document->title }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Tipe Dokumen:</strong> {{ $document->document_type }}</p>
                    <p><strong>Nomor Dokumen:</strong> {{ $document->document_number ?? 'N/A' }}</p>
                    <p><strong>Tahun Terbit:</strong> {{ $document->issue_year ?? 'N/A' }}</p>
                    <p><strong>Status:</strong> {{ ucfirst($document->status) }}</p>
                    <p><strong>URL Sumber:</strong> <a href="{{ $document->source_url }}" target="_blank">{{ $document->source_url ?? 'N/A' }}</a></p>
                </div>
                <div class="col-md-6">
                    @if($document->file_path)
                        <p><strong>Nama Berkas:</strong> {{ $document->file_name }}</p>
                        <p><strong>Ukuran Berkas:</strong> {{ format_bytes($document->file_size) }}</p>
                        <a href="{{ Storage::url($document->file_path) }}" target="_blank" class="btn btn-primary">Lihat Berkas</a>
                    @else
                        <p><strong>Berkas:</strong> Tidak ada berkas yang diunggah.</p>
                    @endif
                </div>
            </div>

            <hr>

            <h5>Ringkasan/Catatan Dokumen:</h5>
            <div class="p-3 bg-light rounded">
                <p>{{ $document->full_text ?? 'N/A' }}</p>
            </div>

            <hr>

            <h5>Metadata:</h5>
            <div class="p-3 bg-light rounded">
                <pre>{{ json_encode($document->metadata, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="{{ route('admin.legal-documents.edit', $document) }}" class="btn btn-warning">Ubah</a>
            <form action="{{ route('admin.legal-documents.destroy', $document) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');">Hapus</button>
            </form>
            <a href="{{ route('admin.legal-documents.index') }}" class="btn btn-secondary">Kembali ke Daftar</a>
        </div>
    </div>
</div>
@endsection