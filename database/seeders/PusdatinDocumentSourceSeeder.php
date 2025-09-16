<?php

// Create: php artisan make:seeder PusdatinDocumentSourceSeeder

namespace Database\Seeders;

use App\Models\DocumentSource;
use Illuminate\Database\Seeder;

class PusdatinDocumentSourceSeeder extends Seeder
{
    public function run(): void
    {
        // Main internal document source for Pusdatin
        DocumentSource::updateOrCreate(
            ['name' => 'Pusdatin Internal Documents'],
            [
                'type' => 'manual',
                'base_url' => null,
                'config' => [
                    'department' => 'Pusdatin - Kementerian Luar Negeri',
                    'upload_path' => 'documents',
                    'allowed_types' => ['pdf', 'doc', 'docx'],
                    'max_size_mb' => 10,
                    'document_categories' => [
                        'Nota Kesepahaman - MOU',
                        'Nota Kesepahaman - PKS',
                        'Dokumen Lainnya',
                        'MoU',
                        'PKS',
                        'Agreement',
                        'Surat Edaran',
                        'Pedoman',
                        'SOP',
                    ],
                ],
                'is_active' => true,
                'description' => 'Internal documents uploaded by Pusdatin staff including MoU, PKS, and other cooperation documents',
                'total_documents' => 0,
            ]
        );

        // Optional: Create specific source for different document types
        DocumentSource::updateOrCreate(
            ['name' => 'MoU dan PKS Pusdatin'],
            [
                'type' => 'manual',
                'base_url' => null,
                'config' => [
                    'category' => 'cooperation_agreements',
                    'document_types' => ['Nota Kesepahaman - MOU', 'Nota Kesepahaman - PKS', 'MoU', 'PKS', 'Agreement'],
                    'department' => 'Pusdatin',
                ],
                'is_active' => true,
                'description' => 'Memorandum of Understanding dan Perjanjian Kerja Sama yang dikelola Pusdatin',
                'total_documents' => 0,
            ]
        );
    }
}

// Run: php artisan db:seed --class=PusdatinDocumentSourceSeeder
