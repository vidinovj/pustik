<?php

// app/Services/TikTermsService.php

namespace App\Services;

class TikTermsService
{
    /**
     * Comprehensive TIK terms with relevance scoring for Indonesian regulations
     * Higher scores indicate stronger TIK relevance
     */
    private static array $tikTerms = [
        // Core Technology & Information (High Priority)
        'teknologi informasi' => 10,
        'teknologi informasi dan komunikasi' => 10,
        'sistem elektronik' => 9,
        'transaksi elektronik' => 9,
        'sistem informasi' => 9,
        'keamanan siber' => 10,
        'cyber security' => 10,
        'cybersecurity' => 10,

        // Digital Government & SPBE (Very High Priority)
        'spbe' => 10,
        'sistem pemerintahan berbasis elektronik' => 10,
        'transformasi digital' => 9,
        'digitalisasi' => 9,
        'digital governance' => 9,
        'e-government' => 8,
        'e-governance' => 8,
        'pemerintahan digital' => 9,
        'layanan digital' => 8,
        'administrasi digital' => 8,

        // Data & Privacy
        'data pribadi' => 8,
        'perlindungan data' => 8,
        'data protection' => 8,
        'big data' => 7,
        'data analytics' => 7,
        'data center' => 6,
        'data mining' => 6,
        'basis data' => 6,
        'database' => 6,

        // Internet & Communications
        'telekomunikasi' => 8,
        'internet' => 6,
        'jaringan' => 5,
        'internet of things' => 7,
        'iot' => 7,
        'broadband' => 6,
        'fiber optik' => 6,
        'satelit komunikasi' => 6,

        // E-Commerce & Digital Economy
        'e-commerce' => 7,
        'e-business' => 7,
        'perdagangan elektronik' => 7,
        'ekonomi digital' => 8,
        'fintech' => 7,
        'financial technology' => 7,
        'blockchain' => 6,
        'cryptocurrency' => 6,
        'digital payment' => 7,
        'pembayaran digital' => 7,

        // Artificial Intelligence & Modern Tech
        'artificial intelligence' => 8,
        'machine learning' => 7,
        'kecerdasan buatan' => 8,
        'robot' => 6,
        'otomasi' => 6,
        'automation' => 6,

        // Cloud & Infrastructure
        'cloud computing' => 7,
        'komputasi awan' => 7,
        'server' => 5,
        'hosting' => 5,
        'data center' => 6,
        'pusat data' => 6,
        'infrastruktur digital' => 8,
        'infrastruktur teknologi' => 8,

        // Digital Services & Applications
        'aplikasi' => 5,
        'software' => 5,
        'perangkat lunak' => 5,
        'platform digital' => 7,
        'sistem operasi' => 5,
        'mobile application' => 6,
        'aplikasi mobile' => 6,
        'web application' => 6,
        'aplikasi web' => 6,

        // Security & Compliance
        'keamanan informasi' => 9,
        'information security' => 9,
        'keamanan data' => 8,
        'enkripsi' => 7,
        'encryption' => 7,
        'sertifikat digital' => 7,
        'digital certificate' => 7,
        'tanda tangan digital' => 8,
        'digital signature' => 8,

        // Standards & Interoperability
        'interoperabilitas' => 7,
        'interoperability' => 7,
        'standar' => 5,
        'standard' => 5,
        'protokol' => 5,
        'integration' => 6,
        'integrasi sistem' => 6,

        // Digital Identity & Authentication
        'identitas digital' => 8,
        'digital identity' => 8,
        'autentikasi' => 7,
        'authentication' => 7,
        'nik elektronik' => 7,
        'ktp elektronik' => 7,
        'e-ktp' => 7,

        // Digital Innovation & Startups
        'startup' => 6,
        'inovasi digital' => 7,
        'digital innovation' => 7,
        'inkubator teknologi' => 6,
        'tech incubator' => 6,

        // General Technology Terms (Lower Priority)
        'informatika' => 7,
        'komputer' => 5,
        'computer' => 5,
        'digital' => 6,
        'elektronik' => 6,
        'teknologi' => 5,
        'technology' => 5,
        'tic' => 5,
        'tik' => 5,
    ];

    /**
     * Indonesian agency keywords that suggest TIK relevance
     */
    private static array $tikAgencies = [
        'kementerian komunikasi dan informatika' => 10,
        'kominfo' => 10,
        'kemenkominfo' => 10,
        'bssn' => 10,
        'badan siber dan sandi negara' => 10,
        'kemenpan rb' => 8,
        'kementerian pendayagunaan aparatur negara' => 8,
        'bppt' => 7,
        'brin' => 7,
        'badan riset dan inovasi nasional' => 7,
        'kemenkeu' => 6, // For fintech regulations
        'ojk' => 7, // For financial technology
        'bank indonesia' => 7, // For digital payments
        'bkpm' => 6, // For digital investment
    ];

    /**
     * Document type patterns that often contain TIK content
     */
    private static array $tikDocumentPatterns = [
        'permenkominfo' => 10,
        'peraturan menteri komunikasi dan informatika' => 10,
        'perbssn' => 10,
        'peraturan bssn' => 10,
        'perpres.*digital' => 9,
        'perpres.*elektronik' => 9,
        'pp.*elektronik' => 8,
        'pp.*digital' => 8,
        'uu.*informasi' => 10,
        'uu.*elektronik' => 10,
    ];

    /**
     * Calculate TIK relevance score for given text
     */
    public static function calculateTikScore(string $text): int
    {
        $textLower = strtolower($text);
        $score = 0;
        $foundTerms = [];

        // Check main TIK terms
        foreach (self::$tikTerms as $term => $points) {
            if (stripos($textLower, $term) !== false) {
                $score += $points;
                $foundTerms[] = $term;
            }
        }

        // Bonus for multiple term matches
        if (count($foundTerms) > 3) {
            $score += 5; // Multi-term bonus
        }

        return min($score, 100); // Cap at 100
    }

    /**
     * Calculate agency-based TIK relevance
     */
    public static function calculateAgencyTikScore(string $agencyText): int
    {
        $textLower = strtolower($agencyText);
        $score = 0;

        foreach (self::$tikAgencies as $agency => $points) {
            if (stripos($textLower, $agency) !== false) {
                $score = max($score, $points); // Take highest agency score
            }
        }

        return $score;
    }

    /**
     * Check if document type suggests TIK content
     */
    public static function isDocumentTypeTikRelated(string $documentType, string $title = ''): bool
    {
        $combinedText = strtolower($documentType.' '.$title);

        foreach (self::$tikDocumentPatterns as $pattern => $minScore) {
            if (preg_match('/'.$pattern.'/i', $combinedText)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract TIK keywords found in text
     */
    public static function extractTikKeywords(string $text): array
    {
        $textLower = strtolower($text);
        $foundKeywords = [];

        foreach (self::$tikTerms as $term => $score) {
            if (stripos($textLower, $term) !== false) {
                $foundKeywords[] = [
                    'term' => $term,
                    'score' => $score,
                    'category' => self::categorizeTerm($term),
                ];
            }
        }

        // Sort by score descending
        usort($foundKeywords, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $foundKeywords;
    }

    /**
     * Categorize TIK terms for better organization
     */
    private static function categorizeTerm(string $term): string
    {
        $categories = [
            'digital_government' => ['spbe', 'digitalisasi', 'transformasi digital', 'e-government', 'pemerintahan digital'],
            'security' => ['keamanan siber', 'cyber security', 'keamanan informasi', 'enkripsi'],
            'data_privacy' => ['data pribadi', 'perlindungan data', 'data protection'],
            'technology' => ['teknologi informasi', 'sistem elektronik', 'artificial intelligence'],
            'digital_economy' => ['e-commerce', 'ekonomi digital', 'fintech', 'blockchain'],
            'infrastructure' => ['cloud computing', 'data center', 'infrastruktur digital'],
        ];

        foreach ($categories as $category => $terms) {
            if (in_array($term, $terms)) {
                return $category;
            }
        }

        return 'general_technology';
    }

    /**
     * Get all TIK terms (for reference)
     */
    public static function getAllTikTerms(): array
    {
        return self::$tikTerms;
    }

    /**
     * Check if document is highly TIK-related (score > threshold)
     */
    public static function isHighlyTikRelated(string $text, int $threshold = 15): bool
    {
        return self::calculateTikScore($text) >= $threshold;
    }

    /**
     * Generate TIK summary for a document
     */
    public static function generateTikSummary(string $title, string $content = '', string $agency = ''): array
    {
        $contentScore = self::calculateTikScore($title.' '.$content);
        $agencyScore = self::calculateAgencyTikScore($agency);
        $totalScore = $contentScore + $agencyScore;

        $keywords = self::extractTikKeywords($title.' '.$content);
        $isHighlyRelated = $totalScore >= 15;

        return [
            'tik_score' => $totalScore,
            'content_score' => $contentScore,
            'agency_score' => $agencyScore,
            'is_highly_tik_related' => $isHighlyRelated,
            'found_keywords' => array_slice($keywords, 0, 10), // Top 10 keywords
            'primary_category' => ! empty($keywords) ? $keywords[0]['category'] : 'general_technology',
            'relevance_level' => self::getRelevanceLevel($totalScore),
        ];
    }

    /**
     * Get human-readable relevance level
     */
    private static function getRelevanceLevel(int $score): string
    {
        if ($score >= 30) {
            return 'very_high';
        }
        if ($score >= 20) {
            return 'high';
        }
        if ($score >= 10) {
            return 'medium';
        }
        if ($score >= 5) {
            return 'low';
        }

        return 'minimal';
    }
}
