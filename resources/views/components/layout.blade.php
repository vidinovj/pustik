{{-- resources/views/components/layout.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <title>Beranda</title>
</head>

<body class="h-100 bg-light">

<div class="min-vh-100">
   <x-navbar></x-navbar>
  
   <x-header>{{$title}}</x-header>

    <main>
      <div class="container-fluid">
        <!-- Your content -->
       {{ $slot }}
      </div>
    </main>

    <!-- Document Quick View Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalLabel">Pratinjau Dokumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="documentModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memuat dokumen...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <a href="#" id="viewFullDocument" class="btn btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt me-1"></i>
                        Lihat Lengkap
                    </a>
                </div>
            </div>
        </div>
    </div>
  </div>

  <script>
    // Document modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        const documentModal = document.getElementById('documentModal');
        const modalBody = document.getElementById('documentModalBody');
        const modalTitle = document.getElementById('documentModalLabel');
        const viewFullButton = document.getElementById('viewFullDocument');

        // Add Font Awesome if not already included
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
            document.head.appendChild(link);
        }

        // Handle quick view button clicks
        document.addEventListener('click', function(e) {
            if (e.target.matches('.btn-quick-view') || e.target.closest('.btn-quick-view')) {
                e.preventDefault();
                const button = e.target.matches('.btn-quick-view') ? e.target : e.target.closest('.btn-quick-view');
                const documentId = button.dataset.documentId;
                const documentUrl = button.dataset.documentUrl;
                
                // Show modal
                const modal = new bootstrap.Modal(documentModal);
                modal.show();
                
                // Update full document link
                viewFullButton.href = documentUrl;
                
                // Load document content
                loadDocumentContent(documentId);
            }
        });

        function loadDocumentContent(documentId) {
            // Reset modal content
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Memuat dokumen...</p>
                </div>
            `;

            // Fetch document content
            fetch(`/documents/${documentId}/content`)
                .then(response => response.json())
                .then(data => {
                    modalTitle.textContent = data.title;
                    
                    let content = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Jenis:</strong> ${data.document_type || '-'}
                            </div>
                            <div class="col-md-6">
                                <strong>Nomor:</strong> ${data.document_number || '-'}
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Tanggal:</strong> ${data.issue_date || '-'}
                            </div>
                        </div>
                    `;

                    if (data.full_text) {
                        content += `
                            <div class="mt-3">
                                <h6>Isi Dokumen:</h6>
                                <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto; background-color: #f8f9fa;">
                                    <div style="white-space: pre-line; line-height: 1.6;">${data.full_text}</div>
                                </div>
                            </div>
                        `;
                    } else if (data.source_url) {
                        content += `
                            <div class="mt-3">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Dokumen ini tersedia sebagai link eksternal. Klik "Lihat Lengkap" untuk membuka dokumen.
                                </div>
                            </div>
                        `;
                    } else {
                        content += `
                            <div class="mt-3">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Konten dokumen tidak tersedia untuk pratinjau.
                                </div>
                            </div>
                        `;
                    }

                    modalBody.innerHTML = content;
                })
                .catch(error => {
                    console.error('Error loading document:', error);
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Gagal memuat dokumen. Silakan coba lagi.
                        </div>
                    `;
                });
        }
    });
  </script>
  
</body>
</html>