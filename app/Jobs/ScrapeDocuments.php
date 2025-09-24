<?php

namespace App\Jobs;

use App\Models\DocumentSource;
use App\Services\Scrapers\BpkScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScrapeDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The job's ID.
     *
     * @var int|null
     */
    public $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (config('queue.default') !== 'sync') {
            $this->jobId = $this->job->getJobId();
            DB::table('jobs')->where('id', $this->jobId)->update(['name' => 'Scrape Documents', 'status' => 'running', 'progress' => 0]);
        }

        Log::info("Starting BPK Scraper Job (ID: {$this->jobId})...");

        try {
            $source = DocumentSource::where('name', 'BPK')->first();
            if (!$source) {
                Log::error("BPK DocumentSource not found for job ID: {$this->jobId}.");
                if ($this->jobId) {
                    DB::table('jobs')->where('id', $this->jobId)->update(['status' => 'failed']);
                }
                return;
            }
            
            $scraper = new BpkScraper($source);
            $scraper->setJob($this);
            $documents = $scraper->scrape();

            if ($this->jobId) {
                DB::table('jobs')->where('id', $this->jobId)->update(['status' => 'completed', 'progress' => 100]);
            }
            Log::info("BPK Scraper Job (ID: {$this->jobId}) finished successfully.");
        } catch (\Exception $e) {
            if ($this->jobId) {
                DB::table('jobs')->where('id', $this->jobId)->update(['status' => 'failed']);
            }
            Log::error("Error during BPK Scraper Job (ID: {$this->jobId}): " . $e->getMessage());
        }
    }

    /**
     * Update the job's progress.
     *
     * @param int $progress
     */
    public function updateProgress(int $progress): void
    {
        if ($this->jobId) {
            DB::table('jobs')->where('id', $this->jobId)->update(['progress' => $progress]);
        }
    }
}