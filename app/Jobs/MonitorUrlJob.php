<?php

namespace App\Jobs;

use App\Models\UrlMonitoring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitorUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $url;

    /**
     * Job timeout in seconds.
     */
    public int $timeout = 60;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(string $url)
    {
        $this->url = $url;
        $this->onQueue(config('legal_documents.processing.queue_name', 'legal-documents'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $monitoring = UrlMonitoring::monitor($this->url);
        
        try {
            Log::channel('legal-documents')->info("Monitoring URL: {$this->url}");

            $response = Http::urlChecker()->get($this->url);

            if ($response->successful()) {
                $monitoring->markAsWorking($response->status());
                Log::channel('legal-documents')->info("URL is working: {$this->url} (HTTP {$response->status()})");
            } else {
                $monitoring->markAsBroken($response->status(), "HTTP {$response->status()}");
                Log::channel('legal-documents')->warning("URL is broken: {$this->url} (HTTP {$response->status()})");
                
                // Send notification if threshold reached
                if ($monitoring->shouldNotify()) {
                    $this->sendBrokenUrlNotification($monitoring);
                }
            }

        } catch (\Exception $e) {
            $monitoring->markAsBroken(0, $e->getMessage());
            Log::channel('legal-documents-errors')->error("URL monitoring failed for {$this->url}: {$e->getMessage()}");
            
            // Send notification if threshold reached
            if ($monitoring->shouldNotify()) {
                $this->sendBrokenUrlNotification($monitoring);
            }
        }
    }

    /**
     * Send notification about broken URL.
     */
    protected function sendBrokenUrlNotification(UrlMonitoring $monitoring): void
    {
        Log::channel('legal-documents-errors')->error("URL has failed {$monitoring->failure_count} times: {$monitoring->url}", [
            'url' => $monitoring->url,
            'failure_count' => $monitoring->failure_count,
            'last_error' => $monitoring->error_message,
        ]);

        // TODO: Implement email/Slack notifications here
        // You could dispatch a notification job or send directly
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'url-monitoring',
            'legal-documents',
        ];
    }
}