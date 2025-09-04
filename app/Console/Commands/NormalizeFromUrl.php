<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class NormalizeFromUrl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:normalize-from-url {--dry-run : Show what would be changed without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize document type, number, and year from BPK source URLs.';

    /**
     * A map of URL type codes to full document type names.
     *
     * @var array
     */
    protected $typeMap = [
        'uu' => 'Undang-Undang',
        'pp' => 'Peraturan Pemerintah',
        'perpres' => 'Peraturan Presiden',
        'permen' => 'Peraturan Menteri',
        'permendagri' => 'Peraturan Menteri',
        'permenkumham' => 'Peraturan Menteri',
        'permendikbud' => 'Peraturan Menteri',
        'permendikbudriset' => 'Peraturan Menteri',
        'permenkes' => 'Peraturan Menteri',
        'permenkeu' => 'Peraturan Menteri',
        'permentan' => 'Peraturan Menteri',
        'permenhub' => 'Peraturan Menteri',
        'permenpan' => 'Peraturan Menteri',
        'permenlu' => 'Peraturan Menteri',
        'permendesa' => 'Peraturan Menteri',
        'permenpera' => 'Peraturan Menteri',
        'permensos' => 'Peraturan Menteri',
        'permenlhk' => 'Peraturan Menteri',
        'permenristekdikti' => 'Peraturan Menteri',
        'permenedag' => 'Peraturan Menteri',
        'permendag' => 'Peraturan Menteri',
        'permenperin' => 'Peraturan Menteri',
        'permenkominfo' => 'Peraturan Menteri',
        'permenkomdigi' => 'Peraturan Menteri',
        'permenkopukm' => 'Peraturan Menteri',
        'permenpopar' => 'Peraturan Menteri',
        'permenjamsos' => 'Peraturan Menteri',
        'permenaker' => 'Peraturan Menteri',
        'permentrans' => 'Peraturan Menteri',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->info('😂 DRY RUN MODE - No changes will be saved.');
        }

        $this->info('Starting normalization from source URLs...');

        $query = LegalDocument::where('source_url', 'like', '%peraturan.bpk.go.id%');
        $count = $query->count();

        if ($count === 0) {
            $this->info('No documents found with a BPK source URL.');
            return;
        }

        $this->info("Found {$count} documents from peraturan.bpk.go.id to process.");
        $progressBar = $this->output->createProgressBar($count);

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $query->chunk(100, function ($documents) use ($progressBar, &$stats, $isDryRun) {
            foreach ($documents as $document) {
                $stats['processed']++;
                $urlPath = parse_url($document->source_url, PHP_URL_PATH);
                $slug = basename($urlPath);

                if (preg_match('/(?<type_code>[a-z]+)-no-(?<number>\d+)(?:.*?(?<year>20\d{2}))?/i', $slug, $matches)) {
                    $typeCode = strtolower($matches['type_code']);
                    $documentType = $this->typeMap[$typeCode] ?? null;
                    $documentNumber = $matches['number'];
                    $issueYear = $matches['year'] ?? null;

                    if (!$documentType) {
                        $this->warn("\nSkipping document ID {$document->id}: Unknown type code '{$typeCode}'.");
                        $stats['skipped']++;
                        continue;
                    }

                    $changes = [];
                    if ($document->document_type !== $documentType) {
                        $changes['document_type'] = ['from' => $document->document_type, 'to' => $documentType];
                    }
                    if ($document->document_type_code !== $typeCode) {
                        $changes['document_type_code'] = ['from' => $document->document_type_code, 'to' => $typeCode];
                    }
                    if ($document->document_number !== $documentNumber) {
                        $changes['document_number'] = ['from' => $document->document_number, 'to' => $documentNumber];
                    }
                    if ($issueYear && $document->issue_year !== $issueYear) {
                        $changes['issue_year'] = ['from' => $document->issue_year, 'to' => $issueYear];
                    }

                    if (!empty($changes)) {
                        $stats['updated']++;
                        $this->line('\n');
                        $this->info("Updating Document ID: {$document->id} ({$document->title})");
                        foreach ($changes as $field => $change) {
                            $this->line("  - {$field}: '{$change['from']}' -> '{$change['to']}'");
                            if (!$isDryRun) {
                                $document->{$field} = $change['to'];
                            }
                        }
                        if (!$isDryRun) {
                            $document->save();
                        }
                    }
                } else {
                    $stats['skipped']++;
                }
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Normalization complete.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Documents Processed', $stats['processed']],
                ['Documents Updated', $isDryRun ? $stats['updated'] . ' (Dry Run)' : $stats['updated']],
                ['Documents Skipped', $stats['skipped']],
            ]
        );

        if ($isDryRun) {
            $this->warn('This was a dry run. No changes were saved. Run without --dry-run to apply changes.');
        }
    }
}
