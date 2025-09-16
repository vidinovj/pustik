<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container-fluid px-4 py-3">
        @php
        $filters = [
            ['name' => 'jenis_kebijakan', 'label' => 'Jenis Kebijakan', 'type' => 'text', 'placeholder' => 'Jenis Kebijakan', 'width' => 3],
            ['name' => 'nomor_kebijakan', 'label' => 'Nomor Kebijakan', 'type' => 'text', 'placeholder' => 'Nomor Kebijakan', 'width' => 3],
            ['name' => 'tahun_penerbitan_min', 'label' => 'Tahun Penerbitan (awal)', 'type' => 'number', 'placeholder' => 'Min Tahun', 'width' => 3],
            ['name' => 'tahun_penerbitan_max', 'label' => 'Tahun Penerbitan (akhir)', 'type' => 'number', 'placeholder' => 'Max Tahun', 'width' => 3],
            ['name' => 'perihal_kebijakan', 'label' => 'Perihal', 'type' => 'text', 'placeholder' => 'Perihal', 'width' => 4],
            ['name' => 'instansi', 'label' => 'Instansi', 'type' => 'text', 'placeholder' => 'Instansi', 'width' => 4],
            ['name' => 'sort_by', 'label' => 'Urutkan Berdasarkan', 'type' => 'select', 'placeholder' => 'Pilih...', 'options' => ['nomor_kebijakan' => 'Nomor Kebijakan', 'tahun_penerbitan' => 'Tahun Penerbitan'], 'width' => 4],
            ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'select', 'placeholder' => '', 'options' => ['asc' => 'Dari bawah ke atas (NAIK)', 'desc' => 'Dari atas ke bawah (TURUN)'], 'width' => 3],
        ];
        @endphp

        <x-dynamic-filter-form :action="url('kebijakan-eksternal')" :filters="$filters" />

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
