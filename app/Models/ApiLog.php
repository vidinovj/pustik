<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    protected $fillable = [
        'document_source_id',
        'endpoint',
        'request_method',
        'response_status',
        'response_time',
        'error_message',
        'request_payload',
        'response_payload',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'response_time' => 'integer',
    ];

    /**
     * Get the document source that owns this log.
     */
    public function documentSource(): BelongsTo
    {
        return $this->belongsTo(DocumentSource::class);
    }

    /**
     * Scope for successful requests.
     */
    public function scopeSuccessful($query)
    {
        return $query->whereBetween('response_status', [200, 299]);
    }

    /**
     * Scope for failed requests.
     */
    public function scopeFailed($query)
    {
        return $query->where('response_status', '>=', 400);
    }

    /**
     * Scope for recent logs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Create a log entry for an API request.
     */
    public static function logRequest(
        int $sourceId,
        string $endpoint,
        string $method,
        int $status,
        int $responseTime,
        ?string $error = null,
        ?array $requestPayload = null,
        ?array $responsePayload = null
    ): self {
        return static::create([
            'document_source_id' => $sourceId,
            'endpoint' => $endpoint,
            'request_method' => $method,
            'response_status' => $status,
            'response_time' => $responseTime,
            'error_message' => $error,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
        ]);
    }
}