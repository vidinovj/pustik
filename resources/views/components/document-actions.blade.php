{{-- resources/views/components/document-actions.blade.php --}}
@props(['document'])

<div class="btn-group" role="group">
    <!-- Quick View Button -->
    <button type="button" 
            class="btn btn-sm btn-outline-primary btn-quick-view"
            data-document-id="{{ $document->id }}"
            data-document-url="{{ route('documents.show', $document) }}"
            title="Pratinjau Cepat">
        <i class="fas fa-eye"></i>
    </button>

    <!-- Download Button -->
    @if($document->pdf_url || $document->source_url || $document->full_text)
        <a href="{{ route('documents.download', $document) }}" 
           class="btn btn-sm btn-outline-success"
           title="Download {{ $document->pdf_url ? 'PDF' : 'Dokumen' }}">
            <i class="fas fa-download"></i>
            @if($document->pdf_url)
                <small class="ms-1">PDF</small>
            @endif
        </a>
    @endif

    <!-- Direct PDF View (opens in new tab) -->
    @if($document->pdf_url)
        <a href="{{ $document->pdf_url }}" 
           class="btn btn-sm btn-outline-info" 
           target="_blank"
           title="Buka PDF di tab baru">
            <i class="fas fa-external-link-alt"></i>
        </a>
    @endif
</div>