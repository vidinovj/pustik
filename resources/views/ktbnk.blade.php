{{-- resources/views/ktbnk.blade.php --}}
<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container-fluid px-4 py-3">
        <!-- Filter Form -->
        <form method="GET" action="{{ url('ktbnk') }}" class="form-container p-4 mb-4">
            <!-- First Row - Main Filters -->
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
                    <label for="perihal" class="form-label fw-medium text-secondary">Perihal</label>
                    <input 
                        type="text" 
                        id="perihal" 
                        name="perihal" 
                        value="{{ request('perihal') }}" 
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
            <table class="table table-custom table-hover">
                <thead class="thead-custom">
                    <tr>
                        <th scope="col" class="fw-semibold text-uppercase">Jenis Kebijakan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Nomor Kebijakan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Tahun Penerbitan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Perihal</th>
                        <th scope="col" class="fw-semibold text-uppercase">Instansi</th>
                        <th scope="col" class="fw-semibold text-uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="tbody-custom">
                    @forelse($kebijakan as $item)
                        <tr>
                            <td>{{ $item->document_type }}</td>
                            <td>{{ $item->document_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->issue_date)->year }}</td>
                            <td class="text-break">{{ $item->title }}</td>
                            <td>{{ $item->metadata['agency'] ?? '' }}</td>
                            <td>
                                @if ($item->source_url || $item->full_text)
                                    <div class="btn-group" role="group">
                                        <a 
                                            href="{{ route('documents.show', $item) }}" 
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>
                                            Lihat
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-secondary btn-quick-view"
                                            data-document-id="{{ $item->id }}"
                                            data-document-url="{{ route('documents.show', $item) }}"
                                            title="Pratinjau Cepat">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted">Tidak tersedia</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Tidak ada data yang ditemukan.</td>
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