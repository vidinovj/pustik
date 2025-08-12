<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LegalDocument;
use Carbon\Carbon;

class SampleLegalDocumentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data to prevent duplicates on re-run
        LegalDocument::truncate();

        // Sample data for /ktbk (Kebijakan TIK by Kemlu)
        LegalDocument::create([
            'document_type' => 'Peraturan Menteri',
            'document_number' => 'PM.1/2025',
            'title' => 'Peraturan Menteri Luar Negeri tentang Kebijakan TIK',
            'issue_date' => Carbon::parse('2025-01-15'),
            'source_url' => 'http://example.com/pm-kemlu-2025.pdf',
            'full_text' => 'Peraturan Menteri Luar Negeri tentang Kebijakan TIK',
            'metadata' => [
                'agency' => 'Kementerian Luar Negeri',
                'satker_kemlu_terkait' => 'Direktorat Jenderal Informasi dan Diplomasi Publik',
                'kl_external_terkait' => null,
                'tanggal_berakhir' => null,
            ],
        ]);

        // Sample data for /ktbnk (Kebijakan TIK by Non Kemlu)
        LegalDocument::create([
            'document_type' => 'Undang-Undang',
            'document_number' => 'UU No. 10/2024',
            'title' => 'Undang-Undang tentang Keamanan Siber Nasional',
            'issue_date' => Carbon::parse('2024-03-20'),
            'source_url' => 'http://example.com/uu-siber-2024.pdf',
            'full_text' => 'Undang-Undang tentang Keamanan Siber Nasional',
            'metadata' => [
                'agency' => 'Kementerian Komunikasi dan Informatika',
                'satker_kemlu_terkait' => null,
                'kl_external_terkait' => 'Badan Siber dan Sandi Negara',
                'tanggal_berakhir' => null,
            ],
        ]);

        // Sample data for /nkmdp (Nota Kesepahaman - MOU)
        LegalDocument::create([
            'document_type' => 'Nota Kesepahaman - MOU',
            'document_number' => 'MOU/001/2025',
            'title' => 'Nota Kesepahaman antara Kemlu dan Kementerian Kesehatan',
            'issue_date' => Carbon::parse('2025-02-01'),
            'source_url' => 'http://example.com/mou-kemlu-kemenkes-2025.pdf',
            'full_text' => 'Nota Kesepahaman antara Kemlu dan Kementerian Kesehatan',
            'metadata' => [
                'agency' => 'Kementerian Luar Negeri',
                'satker_kemlu_terkait' => 'Direktorat Jenderal Kerja Sama Multilateral',
                'kl_external_terkait' => 'Kementerian Kesehatan',
                'tanggal_berakhir' => Carbon::parse('2027-02-01'), // Example end date
            ],
        ]);

        // Sample data for /nkmdp (Nota Kesepahaman - PKS)
        LegalDocument::create([
            'document_type' => 'Nota Kesepahaman - PKS',
            'document_number' => 'PKS/005/2025',
            'title' => 'Perjanjian Kerja Sama dengan Universitas Gadjah Mada',
            'issue_date' => Carbon::parse('2025-04-10'),
            'source_url' => 'http://example.com/pks-kemlu-ugm-2025.pdf',
            'full_text' => 'Perjanjian Kerja Sama dengan Universitas Gadjah Mada',
            'metadata' => [
                'agency' => 'Kementerian Luar Negeri',
                'satker_kemlu_terkait' => 'Pusat Pendidikan dan Pelatihan',
                'kl_external_terkait' => 'Universitas Gadjah Mada',
                'tanggal_berakhir' => Carbon::parse('2028-04-10'), // Example end date
            ],
        ]);
    }
}