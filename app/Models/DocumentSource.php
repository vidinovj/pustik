<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSource extends Model
{
    protected $fillable = [
        'name',
        'type',
        'base_url',
        'config',
        'is_active',
        'last_scraped_at',
        'total_documents',
        'description',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
    ];

    /**
     * Get all legal documents from this source.
     */
    public function legalDocuments(): HasMany
    {
        return $this->hasMany(LegalDocument::class);
    }

    /**
     * Get all API logs for this source.
     */
    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    /**
     * Scope for active sources only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific source types.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get configuration value by key.
     */
    public function getConfig(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Set configuration value.
     */
    public function setConfig(string $key, $value): void
    {
        $config = $this->config ?? [];
        data_set($config, $key, $value);
        $this->config = $config;
    }

    /**
     * Update last scraped timestamp.
     */
    public function markAsScraped(): void
    {
        $this->update(['last_scraped_at' => now()]);
    }

    /**
     * Increment document count.
     */
    public function incrementDocumentCount(int $count = 1): void
    {
        $this->increment('total_documents', $count);
    }

    /**
     * Check if source needs scraping based on configuration.
     */
    public function needsScraping(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $interval = $this->getConfig('scrape_interval', '24 hours');
        $lastScraped = $this->last_scraped_at;

        if (!$lastScraped) {
            return true;
        }

        return $lastScraped->diffInHours(now()) >= 24; // Default 24 hours
    }
}