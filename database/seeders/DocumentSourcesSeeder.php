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
            [
                'name' => 'jdih_kemlu',
                'type' => 'web_scraping',
                'base_url' => 'https://jdih.kemlu.go.id',
                'description' => 'Jaringan Dokumentasi dan Informasi Hukum Kementerian Luar Negeri',
                'is_active' => true,
                'config' => [
                    'request_delay' => 2,
                    'timeout' => 30,
                    'max_pages' => 10,
                    'scrape_interval' => '24 hours',
                    'headers' => [
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ],
                    'document_types' => [
                        'Permenlu' => 'Peraturan Menteri Luar Negeri',
                        'Kepdirjen' => 'Keputusan Direktur Jenderal',
                        'Kepmenko' => 'Keputusan Menteri Koordinator',
                        'Surat+Edaran' => 'Surat Edaran',
                    ],
                ],
            ],
            [
                'name' => 'jdihn',
                'type' => 'web_scraping',
                'base_url' => 'https://jdihn.go.id',
                'description' => 'Jaringan Dokumentasi dan Informasi Hukum Nasional',
                'is_active' => true,
                'config' => [
                    'request_delay' => 3,
                    'timeout' => 45,
                    'max_pages' => 20,
                    'scrape_interval' => '6 hours',
                    'search_keywords' => [
                        'teknologi informasi',
                        'telekomunikasi',
                        'data pribadi',
                        'keamanan siber',
                        'digital',
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
                    'max_pages' => 15,
                    'scrape_interval' => '12 hours',
                    'focus_areas' => [
                        'teknologi informasi',
                        'sistem informasi',
                        'audit teknologi',
                    ],
                ],
            ],
            [
                'name' => 'kominfo_jdih',
                'type' => 'web_scraping',
                'base_url' => 'https://jdih.komdigi.go.id',
                'description' => 'JDIH Kementerian Komunikasi dan Digital',
                'is_active' => true,
                'config' => [
                    'request_delay' => 2,
                    'timeout' => 30,
                    'max_pages' => 25,
                    'scrape_interval' => '4 hours',
                    'priority_areas' => [
                        'telekomunikasi',
                        'teknologi informasi',
                        'keamanan informasi',
                        'data pribadi',
                        'e-government',
                    ],
                ],
            ],
            [
                'name' => 'jdih_perpusnas_api',
                'type' => 'api',
                'base_url' => 'https://api-jdih.perpusnas.go.id',
                'description' => 'API JDIH Perpustakaan Nasional RI',
                'is_active' => true,
                'config' => [
                    'api_version' => 'v1',
                    'bearer_token' => '123ABC-demoonly',
                    'x_api_key' => '123ABC',
                    'rate_limit' => 60,
                    'timeout' => 30,
                    'endpoints' => [
                        'articles' => '/list-artikel',
                        'search' => '/list-artikel',
                        'token' => '/token',
                    ],
                ],
            ],
            [
                'name' => 'bssn_regulations',
                'type' => 'web_scraping',
                'base_url' => 'https://www.bssn.go.id',
                'description' => 'Badan Siber dan Sandi Negara - Regulasi Keamanan Siber',
                'is_active' => false, // Will implement later
                'config' => [
                    'request_delay' => 3,
                    'timeout' => 30,
                    'focus_areas' => [
                        'keamanan siber',
                        'sandi negara',
                        'infrastruktur informasi kritis',
                    ],
                ],
            ],
            [
                'name' => 'menpan_spbe',
                'type' => 'web_scraping',
                'base_url' => 'https://www.menpan.go.id',
                'description' => 'Kementerian PANRB - SPBE dan Reformasi Birokrasi',
                'is_active' => false, // Will implement later
                'config' => [
                    'request_delay' => 2,
                    'timeout' => 30,
                    'focus_areas' => [
                        'sistem pemerintahan berbasis elektronik',
                        'reformasi birokrasi',
                        'inovasi pelayanan publik',
                    ],
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
    }
}