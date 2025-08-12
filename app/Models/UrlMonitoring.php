<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UrlMonitoring extends Model
{
    use HasUuids;
    protected $table = 'url_monitoring';

    protected $fillable = [
        'url',
        'last_checked_at',
        'status',
        'http_status_code',
        'error_message',
        'failure_count',
        'last_successful_check_at',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'last_successful_check_at' => 'datetime',
    ];

    /**
     * Scope for active URLs (working).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for broken URLs.
     */
    public function scopeBroken($query)
    {
        return $query->where('status', 'broken');
    }

    /**
     * Scope for URLs needing check.
     */
    public function scopeNeedsCheck($query, int $hours = 24)
    {
        return $query->where(function ($q) use ($hours) {
            $q->whereNull('last_checked_at')
              ->orWhere('last_checked_at', '<', now()->subHours($hours));
        });
    }

    /**
     * Mark URL as working.
     */
    public function markAsWorking(int $httpStatus = 200): void
    {
        $this->update([
            'status' => 'active',
            'http_status_code' => $httpStatus,
            'last_checked_at' => now(),
            'last_successful_check_at' => now(),
            'failure_count' => 0,
            'error_message' => null,
        ]);
    }

    /**
     * Mark URL as broken.
     */
    public function markAsBroken(int $httpStatus, string $error = null): void
    {
        $this->update([
            'status' => 'broken',
            'http_status_code' => $httpStatus,
            'last_checked_at' => now(),
            'failure_count' => $this->failure_count + 1,
            'error_message' => $error,
        ]);
    }

    /**
     * Add or update URL monitoring.
     */
    public static function monitor(string $url): self
    {
        return static::firstOrCreate(
            ['url' => $url],
            [
                'status' => 'pending',
                'failure_count' => 0,
            ]
        );
    }

    /**
     * Check if URL should trigger notifications.
     */
    public function shouldNotify(): bool
    {
        $threshold = config('legal_documents.url_monitoring.notification_threshold', 3);
        return $this->failure_count >= $threshold;
    }
}