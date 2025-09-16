<?php

namespace Database\Seeders;

use App\Models\DocumentSource;
use Illuminate\Database\Seeder;

class DocumentSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            // WORKING SITES (Priority)
            [
                'name' => 'peraturan_go_id',
                'type' => 'web_scraping',
                'base_url' => 'https://peraturan.go.id',
                'description' => 'JDIH Nasional - Database Peraturan Indonesia',
                'is_active' => true,
                'config' => [
                    'request_delay' => 2,
                    'timeout' => 30,
                    'max_documents_per_search' => 10,
                    'scrape_interval' => '6 hours',
                    'search_terms' => [
                        'teknologi informasi',
                        'telekomunikasi',
                        'data pribadi',
                        'keamanan siber',
                        'sistem informasi',
                        'digital',
                        'elektronik',
                    ],
                ],
            ],
            [
                'name' => 'bpk_jdih',
                'type' => 'web_scraping',
                'base_url' => 'https://peraturan.bpk.go.id',
                'description' => 'Database Peraturan Badan Pemeriksa Keuangan',
                'is_active' => true,
                'config' => [
                    'request_delay' => 2,
                    'timeout' => 30,
                    'max_documents_per_search' => 15,
                    'scrape_interval' => '12 hours',
                    'focus_areas' => [
                        'teknologi informasi',
                        'sistem informasi',
                        'audit teknologi',
                        'keamanan sistem',
                    ],
                ],
            ],
            [
                'name' => 'menpan_rb',
                'type' => 'web_scraping',
                'base_url' => 'https://www.menpan.go.id',
                'description' => 'Kementerian PANRB - SPBE dan Reformasi Birokrasi',
                'is_active' => true,
                'config' => [
                    'request_delay' => 2,
                    'timeout' => 30,
                    'max_documents_per_search' => 10,
                    'focus_areas' => [
                        'sistem pemerintahan berbasis elektronik',
                        'reformasi birokrasi',
                        'inovasi pelayanan publik',
                        'teknologi informasi pemerintah',
                    ],
                ],
            ],

            // PROBLEMATIC SITES (Disabled for now)
            [
                'name' => 'jdih_kemlu',
                'type' => 'web_scraping',
                'base_url' => 'https://jdih.kemlu.go.id',
                'description' => 'Jaringan Dokumentasi dan Informasi Hukum Kementerian Luar Negeri',
                'is_active' => false, // Disabled due to timeouts
                'config' => [
                    'request_delay' => 5,
                    'timeout' => 60,
                    'max_pages' => 5,
                    'scrape_interval' => '24 hours',
                    'status' => 'Site experiencing timeouts and connectivity issues',
                ],
            ],
            [
                'name' => 'jdihn',
                'type' => 'web_scraping',
                'base_url' => 'https://jdihn.go.id',
                'description' => 'Jaringan Dokumentasi dan Informasi Hukum Nasional',
                'is_active' => false, // Disabled due to timeouts
                'config' => [
                    'request_delay' => 3,
                    'timeout' => 45,
                    'max_pages' => 20,
                    'scrape_interval' => '6 hours',
                    'status' => 'Site experiencing connectivity issues',
                ],
            ],
            [
                'name' => 'komdigi_jdih',
                'type' => 'web_scraping',
                'base_url' => 'https://jdih.komdigi.go.id',
                'description' => 'JDIH Kementerian Komunikasi dan Digital',
                'is_active' => false, // Disabled due to DNS issues
                'config' => [
                    'request_delay' => 2,
                    'timeout' => 30,
                    'max_pages' => 25,
                    'scrape_interval' => '4 hours',
                    'status' => 'DNS resolution issues',
                ],
            ],

            // API SOURCES (For future use)
            [
                'name' => 'jdih_perpusnas_api',
                'type' => 'api',
                'base_url' => 'https://api-jdih.perpusnas.go.id',
                'description' => 'API JDIH Perpustakaan Nasional RI',
                'is_active' => false, // Disabled - API doesn't exist
                'config' => [
                    'api_version' => 'v1',
                    'bearer_token' => '123ABC-demoonly',
                    'x_api_key' => '123ABC',
                    'rate_limit' => 60,
                    'timeout' => 30,
                    'status' => 'API endpoint does not exist (DNS resolution fails)',
                ],
            ],

            // FUTURE IMPLEMENTATION
            [
                'name' => 'bssn_regulations',
                'type' => 'web_scraping',
                'base_url' => 'https://www.bssn.go.id',
                'description' => 'Badan Siber dan Sandi Negara - Regulasi Keamanan Siber',
                'is_active' => false,
                'config' => [
                    'request_delay' => 3,
                    'timeout' => 30,
                    'status' => 'Returns HTTP 403 - needs authentication or different approach',
                ],
            ],
        ];

        foreach ($sources as $sourceData) {
            DocumentSource::updateOrCreate(
                ['name' => $sourceData['name']],
                $sourceData
            );
        }

        $this->command->info('Document sources seeded successfully.');

        // Show summary
        $active = DocumentSource::where('is_active', true)->count();
        $inactive = DocumentSource::where('is_active', false)->count();

        $this->command->info("Active sources: {$active}");
        $this->command->info("Inactive sources: {$inactive}");
        $this->command->line('');
        $this->command->info('✅ Working sources ready for scraping:');

        DocumentSource::where('is_active', true)->each(function ($source) {
            $this->command->line("  • {$source->name}: {$source->base_url}");
        });
    }
}
