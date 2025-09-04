{{-- resources/views/nkmdp.blade.php --}}
<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container-fluid px-4 py-3">
        <!-- Filter Form -->
        <form method="GET" action="{{ url('nkmdp') }}" class="form-container p-4 mb-4">
            <!-- First Row - Document & Related Info -->
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label for="jenis_dokumen" class="form-label fw-medium text-secondary">Jenis Dokumen</label>
                    <input 
                        type="text" 
                        id="jenis_dokumen" 
                        name="jenis_dokumen" 
                        value="{{ request('jenis_dokumen') }}" 
                        placeholder="Jenis Dokumen" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="perihal_dokumen" class="form-label fw-medium text-secondary">Perihal Dokumen</label>
                    <input 
                        type="text" 
                        id="perihal_dokumen" 
                        name="perihal_dokumen" 
                        value="{{ request('perihal_dokumen') }}" 
                        placeholder="Perihal Dokumen" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="satker_kemlu_terkait" class="form-label fw-medium text-secondary">Satker Kemlu Terkait</label>
                    <input 
                        type="text" 
                        id="satker_kemlu_terkait" 
                        name="satker_kemlu_terkait" 
                        value="{{ request('satker_kemlu_terkait') }}" 
                        placeholder="Satker Kemlu Terkait" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="kl_external_terkait" class="form-label fw-medium text-secondary">K/L/I External Terkait</label>
                    <input 
                        type="text" 
                        id="kl_external_terkait" 
                        name="kl_external_terkait" 
                        value="{{ request('kl_external_terkait') }}" 
                        placeholder="K/L/I External Terkait" 
                        class="form-control">
                </div>
            </div>

            <!-- Second Row - Date Ranges -->
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label for="start_date_disahkan" class="form-label fw-medium text-secondary">Tahun Disahkan (Awal)</label>
                    <input 
                        type="number" 
                        id="start_date_disahkan" 
                        name="start_date_disahkan" 
                        value="{{ request('start_date_disahkan') }}" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="end_date_disahkan" class="form-label fw-medium text-secondary">Tahun Disahkan (Akhir)</label>
                    <input 
                        type="number" 
                        id="end_date_disahkan" 
                        name="end_date_disahkan" 
                        value="{{ request('end_date_disahkan') }}" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="start_date_berakhir" class="form-label fw-medium text-secondary">Tanggal Berakhir (Awal)</label>
                    <input 
                        type="date" 
                        id="start_date_berakhir" 
                        name="start_date_berakhir" 
                        value="{{ request('start_date_berakhir') }}" 
                        class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="end_date_berakhir" class="form-label fw-medium text-secondary">Tanggal Berakhir (Akhir)</label>
                    <input 
                        type="date" 
                        id="end_date_berakhir" 
                        name="end_date_berakhir" 
                        value="{{ request('end_date_berakhir') }}" 
                        class="form-control">
                </div>
            </div>

            <!-- Third Row - Sorting and Submit -->
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="sort_by" class="form-label fw-medium text-secondary">Sortir Berdasarkan</label>
                    <select 
                        id="sort_by" 
                        name="sort_by" 
                        class="form-select" 
                        onchange="this.form.submit()">
                        <option value="" disabled selected>Pilih</option>
                        <option value="tanggal_disahkan_asc" {{ request('sort_by') == 'tanggal_disahkan_asc' ? 'selected' : '' }}>Tahun Disahkan (Terbaru)</option>
                        <option value="tanggal_disahkan_desc" {{ request('sort_by') == 'tanggal_disahkan_desc' ? 'selected' : '' }}>Tahun Disahkan (Terlama)</option>
                        <option value="tanggal_berakhir_asc" {{ request('sort_by') == 'tanggal_berakhir_asc' ? 'selected' : '' }}>Tanggal Berakhir (Terbaru)</option>
                        <option value="tanggal_berakhir_desc" {{ request('sort_by') == 'tanggal_berakhir_desc' ? 'selected' : '' }}>Tanggal Berakhir (Terlama)</option>
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
                        <th scope="col" class="fw-semibold text-uppercase">No</th>
                        <th scope="col" class="fw-semibold text-uppercase">MoU/PKS</th>
                        <th scope="col" class="fw-semibold text-uppercase">Perihal Dokumen</th>
                        <th scope="col" class="fw-semibold text-uppercase">Satker Kemlu Terkait</th>
                        <th scope="col" class="fw-semibold text-uppercase">K/L/I External Terkait</th>
                        <th scope="col" class="fw-semibold text-uppercase">Tahun Disahkan</th>
                        <th scope="col" class="fw-semibold text-uppercase">Tanggal Berakhir</th>
                        <th scope="col" class="fw-semibold text-uppercase">Status</th>
                        <th scope="col" class="fw-semibold text-uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="tbody-custom">
                    @forelse($notaKesepahaman as $index => $nota)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $nota->document_type }}</td>
                            <td class="text-break">{{ $nota->title }}</td>
                            <td>{{ $nota->metadata['satker_kemlu_terkait'] ?? '' }}</td>
                            <td>{{ $nota->metadata['kl_external_terkait'] ?? '' }}</td>
                            <td>{{ $nota->issue_year }}</td>
                            <td>
                                @if(isset($nota->metadata['tanggal_berakhir']))
                                    {{ \Carbon\Carbon::parse($nota->metadata['tanggal_berakhir'])->format('d-m-Y') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($nota->metadata['tanggal_berakhir']))
                                    @php
                                        $tanggalBerakhir = \Carbon\Carbon::parse($nota->metadata['tanggal_berakhir']);
                                        $sekarang = \Carbon\Carbon::now();
                                    @endphp
                                    @if($tanggalBerakhir->isFuture())
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-danger">Berakhir</span>
                                    @endif
                                @else
                                    <span class="badge bg-secondary">Tidak Diketahui</span>
                                @endif
                            </td>
                            <td>
                                @if ($nota->pdf_url || $nota->source_url || $nota->full_text)
                                    <div class="btn-group" role="group">
                                        <!-- View Details Button -->
                                        <a href="{{ route('documents.show', $nota) }}" class="btn btn-sm btn-outline-secondary" title="Lihat Detail">
                                            <i class="fas fa-info-circle"></i>
                                        </a>

                                        <!-- Quick View Modal Button -->
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-primary btn-quick-view"
                                            data-document-id="{{ $nota->id }}"
                                            title="Pratinjau Cepat">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- Download Button -->
                                        <a 
                                            href="{{ route('documents.download', $nota) }}" 
                                            class="btn btn-sm btn-outline-success"
                                            title="Download {{ $nota->pdf_url ? 'PDF' : 'Dokumen' }}">
                                            <i class="fas fa-download"></i>
                                        </a>

                                        <!-- Direct PDF View Button (only if PDF exists) -->
                                        @if($nota->pdf_url)
                                            <a 
                                                href="{{ route('documents.pdf-proxy', $nota) }}" 
                                                class="btn btn-sm btn-outline-info" 
                                                target="_blank"
                                                title="Buka PDF di tab baru">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">Tidak tersedia</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Tidak ada data yang ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $notaKesepahaman->links('pagination.bootstrap') }}
        </div>
    </div>
</x-layout>