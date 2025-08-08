<?php

namespace App\Jobs;

use App\Models\DocumentSource;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected DocumentSource $source;
    protected array $options;

    /**
     * Job timeout in seconds.
     */
    public int $timeout = 3600; // 1 hour

    /**
     * Number of times to retry the job.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(DocumentSource $source, array $options = [])
    {
        $this->source = $source;
        $this->options = $options;
        
        // Set queue based on configuration
        $this->onQueue(config('legal_documents.processing.queue_name', 'legal-documents'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('legal-documents')->info("Starting scrape job for source: {$this->source->name}");

        try {
            // Check if source is still active
            if (!$this->source->is_active) {
                Log::channel('legal-documents')->warning("Skipping inactive source: {$this->source->name}");
                return;
            }

            // Create scraper
            $scraper = ScraperFactory::create($this->source);
            
            if (!$scraper) {
                throw new \Exception("Unable to create scraper for source: {$this->source->name}");
            }

            // Run the scraper
            $documents = $scraper->scrape();

            // Log results
            $documentCount = count($documents);
            Log::channel('legal-documents')->info("Scrape job completed for {$this->source->name}: {$documentCount} documents processed");

            // Update source statistics
            $this->source->update([
                'last_scraped_at' => now(),
                'total_documents' => $this->source->legalDocuments()->count(),
            ]);

            // Dispatch follow-up jobs if needed
            $this->dispatchFollowUpJobs($documents);

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Scrape job failed for {$this->source->name}: {$e->getMessage()}", [
                'source_id' => $this->source->id,
                'exception' => $e,
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('legal-documents-errors')->error("Scrape job permanently failed for {$this->source->name}: {$exception->getMessage()}", [
            'source_id' => $this->source->id,
            'exception' => $exception,
        ]);

        // Mark source as having issues
        $this->source->setConfig('last_error', [
            'message' => $exception->getMessage(),
            'time' => now()->toISOString(),
        ]);
        $this->source->save();
    }

    /**
     * Dispatch follow-up jobs like URL monitoring, text processing, etc.
     */
    protected function dispatchFollowUpJobs(array $documents): void
    {
        // Example: Dispatch URL monitoring jobs
        foreach ($documents as $document) {
            if ($document->source_url) {
                MonitorUrlJob::dispatch($document->source_url)->delay(now()->addMinutes(5));
            }
        }

        // Example: Dispatch search indexing job
        if (count($documents) > 0) {
            IndexDocumentsJob::dispatch($this->source->id)->delay(now()->addMinutes(2));
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'scraping',
            'source:' . $this->source->name,
            'legal-documents',
        ];
    }
}