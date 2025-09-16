<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class LegalDocument extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'title',
        'document_type',
        'document_number',
        'issue_year',
        'source_url',
        'pdf_url',
        'metadata',
        'full_text',
        'document_source_id',
        'status',
        'checksum',
        'tik_relevance_score',
        'tik_keywords',
        'is_tik_related',
        'document_type_code',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tik_keywords' => 'array',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get the document source that owns this document.
     */
    public function documentSource(): BelongsTo
    {
        return $this->belongsTo(DocumentSource::class);
    }

    /**
     * Get searchable data for Laravel Scout.
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'full_text' => $this->full_text,
            'metadata' => $this->metadata,
            'issue_year' => $this->issue_year,
        ];
    }

    /**
     * Generate a checksum for duplicate detection.
     */
    public function generateChecksum(): string
    {
        return md5($this->title.$this->document_number.$this->issue_year);
    }

    /**
     * Check if this document is a duplicate.
     */
    public function isDuplicate(): bool
    {
        return static::where('checksum', $this->generateChecksum())
            ->where('id', '!=', $this->id)
            ->exists();
    }

    /**
     * Scope for searching documents by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope for active/published documents.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for documents from specific source.
     */
    public function scopeFromSource($query, string $sourceName)
    {
        return $query->whereHas('documentSource', function ($q) use ($sourceName) {
            $q->where('name', $sourceName);
        });
    }

    /**
     * Check if document has an uploaded file
     */
    public function hasFile(): bool
    {
        return ! empty($this->file_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->file_path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Check if document is internal (uploaded file)
     */
    public function isInternal(): bool
    {
        return $this->documentSource && $this->documentSource->type === 'manual';
    }
}
