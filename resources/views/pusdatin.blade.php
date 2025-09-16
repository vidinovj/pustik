<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container-fluid px-4 py-3">
        @php
        $filters = [
            ['name' => 'jenis_dokumen', 'label' => 'Jenis Dokumen', 'type' => 'text', 'placeholder' => 'Jenis Dokumen', 'width' => 3],
            ['name' => 'perihal_dokumen', 'label' => 'Perihal Dokumen', 'type' => 'text', 'placeholder' => 'Perihal Dokumen', 'width' => 3],
            ['name' => 'satker_kemlu_terkait', 'label' => 'Satker Kemlu Terkait', 'type' => 'text', 'placeholder' => 'Satker Kemlu Terkait', 'width' => 3],
            ['name' => 'kl_external_terkait', 'label' => 'K/L/I External Terkait', 'type' => 'text', 'placeholder' => 'K/L/I External Terkait', 'width' => 3],
            ['name' => 'start_date_disahkan', 'label' => 'Tahun Disahkan (Awal)', 'type' => 'number', 'placeholder' => '', 'width' => 3],
            ['name' => 'end_date_disahkan', 'label' => 'Tahun Disahkan (Akhir)', 'type' => 'number', 'placeholder' => '', 'width' => 3],
            ['name' => 'start_date_berakhir', 'label' => 'Tanggal Berakhir (Awal)', 'type' => 'date', 'placeholder' => '', 'width' => 3],
            ['name' => 'end_date_berakhir', 'label' => 'Tanggal Berakhir (Akhir)', 'type' => 'date', 'placeholder' => '', 'width' => 3],
            ['name' => 'sort_by', 'label' => 'Sortir Berdasarkan', 'type' => 'select', 'placeholder' => 'Pilih', 'options' => [
                'tanggal_disahkan_asc' => 'Tahun Disahkan (Terbaru)',
                'tanggal_disahkan_desc' => 'Tahun Disahkan (Terlama)',
                'tanggal_berakhir_asc' => 'Tanggal Berakhir (Terbaru)',
                'tanggal_berakhir_desc' => 'Tanggal Berakhir (Terlama)',
            ], 'width' => 4],
        ];
        @endphp

        <x-dynamic-filter-form :action="url('pusdatin')" :filters="$filters" />

        <!-- Results Table -->
        <div class="table-responsive">
            <table class="table table-custom table-hover table-striped">
                <thead class="thead-custom">
                    <tr>
                        <th scope="col" class="fw-semibold text-uppercase">No</th>
                        <th scope="col" class="fw-semibold text-uppercase">Jenis Dokumen</th>
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
                    @forelse($documents as $index => $document)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td class="text-break">{{ $document->title }}</td>
                            <td>{{ $document->metadata['satker_kemlu_terkait'] ?? '' }}</td>
                            <td>{{ $document->metadata['kl_external_terkait'] ?? '' }}</td>
                            <td>{{ $document->issue_year }}</td>
                            <td>
                                @if(isset($document->metadata['tanggal_berakhir']))
                                    {{ \Carbon\Carbon::parse($document->metadata['tanggal_berakhir'])->format('d-m-Y') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($document->metadata['tanggal_berakhir']))
                                    @php
                                        $tanggalBerakhir = \Carbon\Carbon::parse($document->metadata['tanggal_berakhir']);
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
                                @if ($document->pdf_url || $document->source_url || $document->full_text)
                                    <div class="btn-group" role="group">
                                        <!-- View Details Button -->
                                        <a href="{{ route('documents.show', $document) }}" class="btn btn-sm btn-outline-secondary" title="Lihat Detail">
                                            <i class="fas fa-info-circle"></i>
                                        </a>

                                        <!-- Quick View Modal Button -->
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-primary btn-quick-view"
                                            data-document-id="{{ $document->id }}"
                                            title="Pratinjau Cepat">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- Download Button -->
                                        <a 
                                            href="{{ route('documents.download', $document) }}" 
                                            class="btn btn-sm btn-outline-success"
                                            title="Download {{ $document->pdf_url ? 'PDF' : 'Dokumen' }}">
                                            <i class="fas fa-download"></i>
                                        </a>

                                        <!-- Direct PDF View Button (only if PDF exists) -->
                                        @if($document->pdf_url)
                                            <a 
                                                href="{{ route('documents.pdf-proxy', $document) }}" 
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
            {{ $documents->links('pagination.bootstrap') }}
        </div>
    </div>
</x-layout>
