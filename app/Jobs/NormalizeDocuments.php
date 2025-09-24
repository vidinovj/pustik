<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeDocuments implements ShouldQueue
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
            DB::table('jobs')->where('id', $this->jobId)->update(['name' => 'Normalize Documents', 'status' => 'running', 'progress' => 0]);
        }

        Log::info("Starting normalization job (ID: {$this->jobId})...");

        try {
            Artisan::call('documents:normalize-columns');
            if ($this->jobId) {
                DB::table('jobs')->where('id', $this->jobId)->update(['progress' => 50]);
            }
            Log::info("documents:normalize-columns command executed successfully for job ID: {$this->jobId}.");

            Artisan::call('documents:normalize-metadata');
            if ($this->jobId) {
                DB::table('jobs')->where('id', $this->jobId)->update(['progress' => 100]);
            }
            Log::info("documents:normalize-metadata command executed successfully for job ID: {$this->jobId}.");

            if ($this->jobId) {
                DB::table('jobs')->where('id', $this->jobId)->update(['status' => 'completed']);
            }
            Log::info("Normalization job (ID: {$this->jobId}) finished successfully.");
        } catch (\Exception $e) {
            if ($this->jobId) {
                DB::table('jobs')->where('id', $this->jobId)->update(['status' => 'failed']);
            }
            Log::error("Error during normalization job (ID: {$this->jobId}): " . $e->getMessage());
        }
    }
}