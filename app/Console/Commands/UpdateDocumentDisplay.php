<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateDocumentDisplay extends Command
{
    protected $signature = 'update:document-display';

    protected $description = 'Update document display to use issue_year instead of issue_date';

    public function handle()
    {
        $this->info('Updating files...');

        $filesToUpdate = [
            app_path('Http/Controllers/DocumentController.php'),
            resource_path('views/documents/show.blade.php'),
            app_path('Services/Scrapers/BaseScraper.php'),
        ];

        foreach ($filesToUpdate as $filePath) {
            if (File::exists($filePath)) {
                $content = File::get($filePath);
                $newContent = $this->getUpdatedContent($filePath, $content);
                File::put($filePath, $newContent);
                $this->info("Updated {$filePath}");
            }
        }

        $this->info('Update complete.');
    }

    private function getUpdatedContent(string $filePath, string $content): string
    {
        switch ($filePath) {
            case app_path('Http/Controllers/DocumentController.php'):
                $content = str_replace(
                    '$document->issue_date?->format(\'d-m-Y\')',
                    '$document->issue_year',
                    $content
                );

                return str_replace(
                    '$document->issue_date ? $document->issue_date->format(\'Y-m-d\') : \'no-date\'',
                    '$document->issue_year ?? \'no-year\'',
                    $content
                );

            case resource_path('views/documents/show.blade.php'):
                $content = str_replace(
                    '@if($document->issue_date)',
                    '@if($document->issue_year)',
                    $content
                );

                return str_replace(
                    '{{ $document->issue_date->format(\'d F Y\') }}',
                    '{{ $document->issue_year }}',
                    $content
                );

            case app_path('Services/Scrapers/BaseScraper.php'):
                $content = str_replace(
                    "('issue_date'] ?? '')",
                    "('issue_year'] ?? '')",
                    $content
                );

                return str_replace(
                    '\'issue_date\' => $documentData[\'issue_date\'] ?? null,',
                    '',
                    $content
                );

            default:
                return $content;
        }
    }
}
