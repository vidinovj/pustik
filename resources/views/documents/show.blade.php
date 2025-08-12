{{-- resources/views/documents/show.blade.php --}}
<x-layout>
    <x-slot:title>{{ $document->title }}</x-slot:title>

    <div class="container-fluid px-4 py-3">
        <!-- Document Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start">
                            <div class="flex-grow-1 mb-3 mb-lg-0">
                                <h1 class="h3 fw-bold text-dark mb-2">{{ $document->title }}</h1>
                                
                                <div class="row g-3">
                                    <div class="col-md-6 col-lg-3">
                                        <small class="text-muted d-block">Jenis Dokumen</small>
                                        <span class="fw-medium">{{ $document->document_type }}</span>
                                    </div>
                                    
                                    @if($document->document_number)
                                    <div class="col-md-6 col-lg-3">
                                        <small class="text-muted d-block">Nomor Dokumen</small>
                                        <span class="fw-medium">{{ $document->document_number }}</span>
                                    </div>
                                    @endif
                                    
                                    @if($document->issue_date)
                                    <div class="col-md-6 col-lg-3">
                                        <small class="text-muted d-block">Tanggal Terbit</small>
                                        <span class="fw-medium">{{ $document->issue_date->format('d F Y') }}</span>
                                    </div>
                                    @endif
                                    
                                    <div class="col-md-6 col-lg-3">
                                        <small class="text-muted d-block">Status</small>
                                        <span class="badge bg-success">{{ ucfirst($document->status) }}</span>
                                    </div>
                                </div>

                                <!-- Additional Metadata -->
                                @if($document->metadata && count($document->metadata) > 0)
                                <div class="mt-3">
                                    <small class="text-muted d-block mb-2">Informasi Tambahan</small>
                                    <div class="row g-2">
                                        @foreach($document->metadata as $key => $value)
                                            @if($value && $key !== 'tanggal_berakhir')
                                            <div class="col-md-6 col-lg-4">
                                                <small class="text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}:</small>
                                                <span class="d-block">
                                                    @if(is_array($value))
                                                        {{ implode(', ', $value) }}
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </span>
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-flex flex-column gap-2">
                                @if($hasSourceUrl)
                                <a href="{{ $document->source_url }}" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i>
                                    Lihat Sumber Asli
                                </a>
                                @endif
                                
                                <a href="{{ route('documents.download', $document) }}" 
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-download me-1"></i>
                                    Download
                                </a>
                                
                                <button type="button" 
                                        class="btn btn-outline-secondary btn-sm"
                                        onclick="window.history.back()">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Kembali
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Content -->
        <div class="row">
            <div class="col-12">
                @if($hasFullText)
                <!-- Full Text Content -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-text me-2"></i>
                            Isi Dokumen
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="document-content" style="line-height: 1.8; font-size: 16px;">
                            @if(is_string($document->full_text))
                                {!! nl2br(e($document->full_text)) !!}
                            @elseif(is_array($document->full_text))
                                {!! nl2br(e(implode("\n", $document->full_text))) !!}
                            @else
                                <p class="text-muted">Konten dokumen tidak dapat ditampilkan.</p>
                            @endif
                        </div>
                    </div>
                </div>
                
                @elseif($hasSourceUrl)
                <!-- External URL Viewer -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-globe me-2"></i>
                            Pratinjau Dokumen
                        </h5>
                        <small class="text-muted">
                            Sumber: {{ parse_url($document->source_url, PHP_URL_HOST) }}
                        </small>
                    </div>
                    <div class="card-body p-0">
                        <div class="ratio ratio-16x9">
                            <iframe src="{{ $document->source_url }}" 
                                    class="border-0" 
                                    title="Document Viewer"
                                    loading="lazy">
                                <div class="p-4 text-center">
                                    <p class="text-muted">Browser Anda tidak mendukung pratinjau dokumen.</p>
                                    <a href="{{ $document->source_url }}" 
                                       target="_blank" 
                                       class="btn btn-primary">
                                        Buka di Tab Baru
                                    </a>
                                </div>
                            </iframe>
                        </div>
                    </div>
                </div>
                
                @else
                <!-- No Content Available -->
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-slash fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Konten Dokumen Tidak Tersedia</h5>
                        <p class="text-muted">
                            Dokumen ini tidak memiliki konten yang dapat ditampilkan saat ini.
                        </p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Related Documents or Additional Info -->
        @if($document->metadata && isset($document->metadata['keterangan']) && $document->metadata['keterangan'])
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Keterangan
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">
                            @if(is_array($document->metadata['keterangan']))
                                {{ implode(', ', $document->metadata['keterangan']) }}
                            @else
                                {{ $document->metadata['keterangan'] }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @push('styles')
    <style>
        .document-content {
            text-align: justify;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .document-content p {
            margin-bottom: 1rem;
        }
        
        iframe {
            min-height: 600px;
        }
        
        @media (max-width: 768px) {
            iframe {
                min-height: 400px;
            }
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .card-header {
            border-bottom: 1px solid #dee2e6;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        // Add Font Awesome if not already included
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
            document.head.appendChild(link);
        }

        // Handle iframe loading errors
        document.addEventListener('DOMContentLoaded', function() {
            const iframe = document.querySelector('iframe');
            if (iframe) {
                iframe.addEventListener('error', function() {
                    console.log('Iframe failed to load, showing fallback');
                    // Could show a fallback message or redirect to external link
                });
            }
        });
    </script>
    @endpush
</x-layout>