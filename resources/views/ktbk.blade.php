{{-- resources/views/ktbk.blade.php --}}
<x-layout>
    <x-slot:title>Kebijakan TIK Internal</x-slot:title>

    <div class="container-fluid px-4 py-3">
        <!-- Filter Form -->
        <form method="GET" action="{{ url('ktbk') }}" class="form-container p-4 mb-4">
            <!-- First Row - Main Filters -->
            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <label for="jenis_kebijakan" class="form-label fw-medium text-secondary">Jenis Kebijakan:</label>
                    <input 
                        type="text" 
                        name="jenis_kebijakan" 
                        id="jenis_kebijakan" 
                        value="{{ request('jenis_kebijakan') }}" 
                        placeholder="Jenis Kebijakan" 
                        class="form-control">
                </div>

                <div class="col-md-2">
                    <label for="nomor_kebijakan" class="form-label fw-medium text-secondary">Nomor Kebijakan:</label>
                    <input 
                        type="text" 
                        name="nomor_kebijakan" 
                        id="nomor_kebijakan" 
                        value="{{ request('nomor_kebijakan') }}" 
                        placeholder="Nomor Kebijakan" 
                        class="form-control">
                </div>

                <div class="col-md-2">
                    <label for="tahun_penerbitan_from" class="form-label fw-medium text-secondary">Tahun (Dari):</label>
                    <input 
                        type="number" 
                        name="tahun_penerbitan_from" 
                        id="tahun_penerbitan_from" 
                        value="{{ request('tahun_penerbitan_from') }}" 
                        placeholder="Dari Tahun" 
                        class="form-control">
                </div>

                <div class="col-md-2">
                    <label for="tahun_penerbitan_to" class="form-label fw-medium text-secondary">Tahun (Sampai):</label>
                    <input 
                        type="number" 
                        name="tahun_penerbitan_to" 
                        id="tahun_penerbitan_to" 
                        value="{{ request('tahun_penerbitan_to') }}" 
                        placeholder="Sampai Tahun" 
                        class="form-control">
                </div>

                <div class="col-md-4">
                    <label for="perihal_kebijakan" class="form-label fw-medium text-secondary">Perihal Kebijakan:</label>
                    <input 
                        type="text" 
                        name="perihal_kebijakan" 
                        id="perihal_kebijakan" 
                        value="{{ request('perihal_kebijakan') }}" 
                        placeholder="Perihal Kebijakan" 
                        class="form-control">
                </div>
            </div>

            <!-- Second Row - Sort Options and Submit -->
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="sort_by" class="form-label fw-medium text-secondary">Urutkan Berdasarkan:</label>
                    <select 
                        name="sort_by" 
                        id="sort_by" 
                        class="form-select"
                        onchange="this.form.submit()">
                        <option value="" {{ request('sort_by') == '' ? 'selected' : '' }}>-- Pilih --</option>
                        <option value="tahun_penerbitan" {{ request('sort_by') == 'tahun_penerbitan' ? 'selected' : '' }}>Tahun Penerbitan</option>
                        <option value="nomor_kebijakan" {{ request('sort_by') == 'nomor_kebijakan' ? 'selected' : '' }}>Nomor Kebijakan</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="sort_order" class="form-label fw-medium text-secondary">Urutan:</label>
                    <select 
                        name="sort_order" 
                        id="sort_order" 
                        class="form-select"
                        onchange="this.form.submit()">
                        <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>Dari bawah ke atas (NAIK)</option>
                        <option value="desc" {{ request('sort_order') == 'desc' ? 'selected' : '' }}>Dari atas ke bawah (TURUN)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button 
                        type="submit" 
                        class="btn btn-filter px-4">
                        Filter
                    </button>
                </div>
            </div>
        </form>

        <!-- Results Table -->
        <div class="table-responsive">
            <table class="table table-custom table-hover table-striped">
                <thead class="thead-custom">
                    <tr>
                        <th scope="col" class="fw-semibold text-uppercase">Jenis Kebijakan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Nomor Kebijakan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Tahun Penerbitan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Perihal Kebijakan</th>
                        <th scope="col" class="fw-semibold text-uppercase text-center" style="width: 160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="tbody-custom">
                    @forelse($kebijakan as $item)
                        <tr>
                            <td class="text-nowrap">{{ $item->document_type }}</td>
                            <td>{{ $item->document_number }}</td>
                            <td>{{ $item->issue_year }}</td>
                            <td class="text-break">
                                {{ $item->title }}
                                
                                {{-- Document type indicator --}}
                                <div class="mt-1">
                                    @if($item->pdf_url)
                                        <span class="badge bg-success bg-opacity-10 text-success">
                                            <i class="fas fa-file-pdf me-1"></i>PDF Tersedia
                                        </span>
                                    @elseif($item->source_url)
                                        <span class="badge bg-info bg-opacity-10 text-info">
                                            <i class="fas fa-link me-1"></i>Link Eksternal
                                        </span>
                                    @elseif($item->full_text)
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                            <i class="fas fa-file-text me-1"></i>Teks Tersedia
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($item->pdf_url || $item->source_url || $item->full_text)
                                    <div class="btn-group" role="group">
                                        <!-- View Details Button -->
                                        <a href="{{ route('documents.show', $item) }}" class="btn btn-sm btn-outline-secondary" title="Lihat Detail">
                                            <i class="fas fa-info-circle"></i>
                                        </a>

                                        <!-- Quick View Modal Button -->
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-primary btn-quick-view"
                                            data-document-id="{{ $item->id }}"
                                            title="Pratinjau Cepat">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- Download Button -->
                                        <a 
                                            href="{{ route('documents.download', $item) }}" 
                                            class="btn btn-sm btn-outline-success"
                                            title="Download {{ $item->pdf_url ? 'PDF' : 'Dokumen' }}">
                                            <i class="fas fa-download"></i>
                                        </a>

                                        <!-- Direct PDF View Button (only if PDF exists) -->
                                        @if($item->pdf_url)
                                            <a 
                                                href="{{ route('documents.pdf-proxy', $item) }}" 
                                                class="btn btn-sm btn-outline-info" 
                                                target="_blank"
                                                title="Buka PDF di tab baru">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        @endif
                                    </div>

                                    {{-- Status indicator below buttons --}}
                                    <div class="mt-1">
                                        @if($item->pdf_url)
                                            <small class="text-success">
                                                <i class="fas fa-file-pdf me-1"></i>PDF
                                            </small>
                                        @elseif($item->source_url)
                                            <small class="text-info">
                                                <i class="fas fa-link me-1"></i>Link
                                            </small>
                                        @elseif($item->full_text)
                                            <small class="text-secondary">
                                                <i class="fas fa-file-text me-1"></i>Teks
                                            </small>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">Tidak tersedia</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Data kebijakan tidak tersedia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $kebijakan->appends(request()->all())->links('pagination.bootstrap') }}
        </div>
    </div>
</x-layout>