{{-- resources/views/ktbk.blade.php --}}
<x-layout>
    <x-slot:title>Kebijakan TIK by Kemlu</x-slot:title>

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
            <table class="table table-custom table-hover">
                <thead class="thead-custom">
                    <tr>
                        <th scope="col" class="fw-semibold text-uppercase">Jenis Kebijakan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Nomor Kebijakan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Tahun Penerbitan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Perihal Kebijakan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="tbody-custom">
                    @forelse($kebijakan as $item)
                        <tr>
                            <td class="text-nowrap">{{ $item->document_type }}</td>
                            <td>{{ $item->document_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->issue_date)->year }}</td>
                            <td class="text-break">{{ $item->title }}</td>
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