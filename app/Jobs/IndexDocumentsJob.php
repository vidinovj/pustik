<?php

namespace App\Jobs;

use App\Models\DocumentSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $sourceId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $sourceId)
    {
        $this->sourceId = $sourceId;
        $this->onQueue(config('legal_documents.processing.queue_name', 'legal-documents'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $source = DocumentSource::find($this->sourceId);

        if (!$source) {
            Log::channel('legal-documents-errors')->warning("IndexDocumentsJob: DocumentSource with ID {$this->sourceId} not found.");
            return;
        }

        Log::channel('legal-documents')->info("Starting indexing job for source: {$source->name}");

        try {
            // Re-index all documents associated with this source
            $source->legalDocuments->searchable();

            Log::channel('legal-documents')->info("Indexing job completed for source: {$source->name}");
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Indexing job failed for {$source->name}: {$e->getMessage()}", [
                'source_id' => $source->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'indexing',
            'source:' . $this->sourceId,
            'legal-documents',
        ];
    }
}
