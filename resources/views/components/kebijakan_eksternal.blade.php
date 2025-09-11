<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container-fluid px-4 py-3">
        <!-- Filter Form -->
        <form method="GET" action="{{ url('kebijakan-eksternal') }}" class="form-container p-4 mb-4">
            <!-- First Row Main Filters -->
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label for="jenis_kebijakan" class="form-label fw-medium text-secondary">Jenis Kebijakan</label>
                    <input 
                        type="text" 
                        id="jenis_kebijakan" 
                        name="jenis_kebijakan" 
                        value="{{ request('jenis_kebijakan') }}" 
                        placeholder="Jenis Kebijakan" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="nomor_kebijakan" class="form-label fw-medium text-secondary">Nomor Kebijakan</label>
                    <input 
                        type="text" 
                        id="nomor_kebijakan" 
                        name="nomor_kebijakan" 
                        value="{{ request('nomor_kebijakan') }}" 
                        placeholder="Nomor Kebijakan" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="tahun_penerbitan_min" class="form-label fw-medium text-secondary">Tahun Penerbitan (awal)</label>
                    <input 
                        type="number" 
                        id="tahun_penerbitan_min" 
                        name="tahun_penerbitan_min" 
                        value="{{ request('tahun_penerbitan_min') }}" 
                        placeholder="Min Tahun" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="tahun_penerbitan_max" class="form-label fw-medium text-secondary">Tahun Penerbitan (akhir)</label>
                    <input 
                        type="number" 
                        id="tahun_penerbitan_max" 
                        name="tahun_penerbitan_max" 
                        value="{{ request('tahun_penerbitan_max') }}" 
                        placeholder="Max Tahun" 
                        class="form-control">
                </div>
            </div>

            <!-- Second Row - Additional Filters -->
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="perihal_kebijakan" class="form-label fw-medium text-secondary">Perihal</label>
                    <input 
                        type="text" 
                        id="perihal_kebijakan" 
                        name="perihal_kebijakan" 
                        value="{{ request('perihal_kebijakan') }}" 
                        placeholder="Perihal" 
                        class="form-control">
                </div>

                <div class="col-md-4">
                    <label for="instansi" class="form-label fw-medium text-secondary">Instansi</label>
                    <input 
                        type="text" 
                        id="instansi" 
                        name="instansi" 
                        value="{{ request('instansi') }}" 
                        placeholder="Instansi" 
                        class="form-control">
                </div>

                <div class="col-md-4">
                    <label for="sort_by" class="form-label fw-medium text-secondary">Urutkan Berdasarkan</label>
                    <select 
                        id="sort_by" 
                        name="sort_by" 
                        class="form-select" 
                        onchange="this.form.submit()">
                        <option value="">Pilih...</option>
                        <option value="nomor_kebijakan" {{ request('sort_by') == 'nomor_kebijakan' ? 'selected' : '' }}>Nomor Kebijakan</option>
                        <option value="tahun_penerbitan" {{ request('sort_by') == 'tahun_penerbitan' ? 'selected' : '' }}>Tahun Penerbitan</option>
                    </select>
                </div>
            </div>

            <!-- Third Row - Sort Order and Submit -->
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="sort_order" class="form-label fw-medium text-secondary">Urutan</label>
                    <select 
                        id="sort_order" 
                        name="sort_order" 
                        class="form-select" 
                        onchange="this.form.submit()">
                        <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>Dari bawah ke atas (NAIK)</option>
                        <option value="desc" {{ request('sort_order') == 'desc' ? 'selected' : '' }}>Dari atas ke bawah (TURUN)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <button 
                        type="submit" 
                        class="btn btn-filter px-4">
                        Filter & Sortir
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
                        <th scope="col" class="fw-semibold text-uppercase">Instansi</th>
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
                            <td>{{ $item->metadata['agency'] ?? 'N/A' }}</td>
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
                            <td colspan="6" class="text-center py-4 text-muted">Data kebijakan tidak tersedia.</td>
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