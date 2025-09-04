// resources/js/app.js
import './bootstrap';

// Import Bootstrap JavaScript
import * as bootstrap from 'bootstrap';

// Make Bootstrap available globally
window.bootstrap = bootstrap;

// Document modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const documentModal = document.getElementById('documentModal');
    // Check if the modal exists on the page
    if (documentModal) {
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
                loadDocumentContent(documentId, modalBody, modalTitle);
            }
        });

        function loadDocumentContent(documentId, modalBody, modalTitle) {
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
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
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
                                <strong>Tahun Terbit:</strong> ${data.issue_year || '-'}
                            </div>
                        </div>
                    `;

                    // Enhanced PDF viewer integration
                    if (data.pdf_url) {
                        const proxyUrl = `/documents/${documentId}/pdf-proxy`;
                        content += `
                            <div class="mt-3">
                                <h6>Preview Dokumen PDF:</h6>
                                <div class="border rounded" style="height: 500px;">
                                    <iframe src="${proxyUrl}" 
                                            width="100%" 
                                            height="100%" 
                                            style="border: none;">
                                        <p>Browser Anda tidak mendukung tampilan PDF. 
                                        <a href="${data.pdf_url}" target="_blank">Klik di sini untuk membuka PDF</a></p>
                                    </iframe>
                                </div>
                            </div>
                        `;
                    } else if (data.full_text) {
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
    }
});