<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class LegalDocument extends Model
{
    use Searchable;

    protected $fillable = [
        'title',
        'document_type',
        'document_number', 
        'issue_date',
        'source_url',
        'metadata',
        'full_text',
        'document_source_id',
        'status',
        'checksum',
    ];

    protected $casts = [
        'metadata' => 'array',
        'issue_date' => 'date',
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
            'issue_date' => $this->issue_date?->format('Y-m-d'),
        ];
    }

    /**
     * Generate a checksum for duplicate detection.
     */
    public function generateChecksum(): string
    {
        return md5($this->title . $this->document_number . $this->issue_date);
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
}