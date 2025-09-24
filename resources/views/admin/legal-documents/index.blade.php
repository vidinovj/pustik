@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div id="job-status-container"></div>

    @php
    $filters = [
        ['name' => 'title', 'label' => 'Perihal', 'type' => 'text', 'placeholder' => 'Saring berdasarkan perihal', 'width' => 3],
        ['name' => 'document_type', 'label' => 'Jenis Dokumen', 'type' => 'text', 'placeholder' => 'Saring berdasarkan jenis', 'width' => 3],
        ['name' => 'document_number', 'label' => 'Nomor Dokumen', 'type' => 'text', 'placeholder' => 'Saring berdasarkan nomor', 'width' => 3],
        ['name' => 'issue_year', 'label' => 'Tahun Terbit', 'type' => 'number', 'placeholder' => 'Saring berdasarkan tahun', 'width' => 3],
    ];
    @endphp

    <x-dynamic-filter-form :action="route('admin.legal-documents.index')" :filters="$filters" />

    <div class="row mb-3">
        <div class="col-md-12 text-end">
            <form action="{{ route('admin.jobs.scrape') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-cloud-download-alt"></i> Scrape Documents
                </button>
            </form>
            <form action="{{ route('admin.jobs.normalize') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-cogs"></i> Normalize Documents
                </button>
            </form>
            <a href="{{ route('admin.legal-documents.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Dokumen Baru
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover table-striped">
            <thead class="thead-custom">
                <tr>
                    <th scope="col" class="fw-semibold text-uppercase">Jenis Dokumen</th>
                    <th scope="col" class="fw-semibold text-uppercase">Nomor Dokumen</th>
                    <th scope="col" class="fw-semibold text-uppercase">Tahun Terbit</th>
                    <th scope="col" class="fw-semibold text-uppercase">Perihal</th>
                    <th scope="col" class="fw-semibold text-uppercase">Instansi</th>
                    <th scope="col" class="fw-semibold text-uppercase">Status</th>
                    <th scope="col" class="fw-semibold text-uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="tbody-custom">
                @forelse ($legal_documents as $legal_document)
                <tr>
                    <td>{{ $legal_document->document_type }}</td>
                    <td>{{ $legal_document->document_number ?? 'N/A' }}</td>
                    <td>{{ $legal_document->issue_year ?? 'N/A' }}</td>
                    <td class="text-break">
                        {{ $legal_document->title }}
                        <div class="mt-1">
                            @if($legal_document->pdf_url)
                                <span class="badge bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-file-pdf me-1"></i>PDF Tersedia
                                </span>
                            @elseif($legal_document->source_url)
                                <span class="badge bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-link me-1"></i>Link Eksternal
                                </span>
                            @elseif($legal_document->full_text)
                                <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                    <i class="fas fa-file-text me-1"></i>Teks Tersedia
                                </span>
                            @endif
                        </div>
                    </td>
                    <td><small>{{ $legal_document->metadata['agency'] ?? 'N/A' }}</small></td>
                    <td>
                        <span class="badge 
                            @if($legal_document->status == 'active') bg-success
                            @elseif($legal_document->status == 'inactive') bg-danger
                            @elseif($legal_document->status == 'pending') bg-warning
                            @else bg-secondary
                            @endif
                        ">{{ ucfirst($legal_document->status) }}</span>
                        
                        @if(isset($legal_document->metadata['tanggal_berakhir']))
                            @php
                                $tanggalBerakhir = \Carbon\Carbon::parse($legal_document->metadata['tanggal_berakhir']);
                                $sekarang = \Carbon\Carbon::now();
                            @endphp
                            <br>
                            <small class="{{ $tanggalBerakhir->isFuture() ? 'text-success' : 'text-danger' }}">
                                Berakhir: {{ $tanggalBerakhir->format('d-m-Y') }}
                            </small>
                        @endif
                    </td>
                    <td class="text-start">
                        <div class="btn-group" role="group">
                            <a href="{{ route('admin.legal-documents.show', $legal_document) }}" class="btn btn-sm btn-outline-primary" title="Lihat"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('admin.legal-documents.edit', $legal_document) }}" class="btn btn-sm btn-outline-warning" title="Ubah"><i class="fas fa-edit"></i></a>
                            <form action="{{ route('admin.legal-documents.destroy', $legal_document) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">Tidak ada dokumen hukum yang ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $legal_documents->links('pagination.bootstrap') }}
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const jobStatusContainer = document.getElementById('job-status-container');

        function fetchJobStatus() {
            fetch('{{ route('admin.jobs.status') }}')
                .then(response => response.json())
                .then(jobs => {
                    jobStatusContainer.innerHTML = '';
                    if (jobs.length > 0) {
                        let html = '<div class="card mb-4"><div class="card-header">Job Status</div><div class="card-body">';
                        jobs.forEach(job => {
                            let statusClass = '';
                            switch (job.status) {
                                case 'completed':
                                    statusClass = 'bg-success';
                                    break;
                                case 'failed':
                                    statusClass = 'bg-danger';
                                    break;
                                case 'running':
                                    statusClass = 'bg-info';
                                    break;
                                default:
                                    statusClass = 'bg-secondary';
                            }
                            html += `
                                <div class="mb-3">
                                    <h6>${job.name} <span class="badge ${statusClass}">${job.status}</span></h6>
                                    <div class="progress">
                                        <div class="progress-bar ${statusClass}" role="progressbar" style="width: ${job.progress}%" aria-valuenow="${job.progress}" aria-valuemin="0" aria-valuemax="100">${job.progress}%</div>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div></div>';
                        jobStatusContainer.innerHTML = html;
                    }
                });
        }

        setInterval(fetchJobStatus, 5000);
        fetchJobStatus();
    });
</script>
@endpush
@endsection