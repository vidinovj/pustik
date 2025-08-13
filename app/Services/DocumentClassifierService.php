<?php
// app/Services/DocumentClassifierService.php

namespace App\Services;

use App\Models\LegalDocument;

class DocumentClassifierService
{
    /**
     * Canonical document categories organized by hierarchy and subject matter
     */
    private static array $canonicalCategories = [
        // Legal Hierarchy Categories (Primary)
        'constitutional_law' => [
            'name' => 'Constitutional Law',
            'description' => 'Constitutional amendments and fundamental law',
            'hierarchy_level' => 1,
            'keywords' => ['konstitusi', 'undang-undang dasar', 'amandemen']
        ],
        
        'primary_law' => [
            'name' => 'Primary Law',
            'description' => 'Undang-undang (Laws passed by Parliament)',
            'hierarchy_level' => 2,
            'keywords' => ['undang-undang', 'uu no', 'dpr ri', 'perubahan atas uu']
        ],
        
        'government_regulation' => [
            'name' => 'Government Regulation',
            'description' => 'Peraturan Pemerintah (Executive implementation)',
            'hierarchy_level' => 3,
            'keywords' => ['peraturan pemerintah', 'pp no', 'pemerintah ri']
        ],
        
        'presidential_regulation' => [
            'name' => 'Presidential Regulation',
            'description' => 'Peraturan Presiden (Presidential directives)',
            'hierarchy_level' => 4,
            'keywords' => ['peraturan presiden', 'perpres', 'presiden ri']
        ],
        
        'ministerial_regulation' => [
            'name' => 'Ministerial Regulation',
            'description' => 'Peraturan Menteri (Ministry-level regulations)',
            'hierarchy_level' => 5,
            'keywords' => ['peraturan menteri', 'permen', 'permenkominfo', 'perbssn']
        ],
        
        'local_regulation' => [
            'name' => 'Local Government Regulation',
            'description' => 'Regional and local government regulations',
            'hierarchy_level' => 6,
            'keywords' => ['perda', 'peraturan daerah', 'gubernur', 'bupati', 'walikota']
        ],
        
        // Subject Matter Categories (TIK-focused)
        'digital_government' => [
            'name' => 'Digital Government & SPBE',
            'description' => 'Electronic-based government systems and digital transformation',
            'subject_area' => 'governance',
            'keywords' => ['spbe', 'sistem pemerintahan berbasis elektronik', 'transformasi digital', 'digitalisasi', 'e-government', 'pemerintahan digital', 'layanan digital', 'one data indonesia']
        ],
        
        'cybersecurity' => [
            'name' => 'Cybersecurity & Information Security',
            'description' => 'Cyber security, information security, and digital defense',
            'subject_area' => 'security',
            'keywords' => ['keamanan siber', 'cyber security', 'keamanan informasi', 'information security', 'bssn', 'cyber defense', 'keamanan data', 'enkripsi']
        ],
        
        'data_privacy' => [
            'name' => 'Data Privacy & Protection',
            'description' => 'Personal data protection and privacy rights',
            'subject_area' => 'privacy',
            'keywords' => ['data pribadi', 'perlindungan data', 'data protection', 'privasi', 'privacy', 'gdpr', 'personal data']
        ],
        
        'electronic_transactions' => [
            'name' => 'Electronic Transactions & ITE',
            'description' => 'Information and Electronic Transactions Law',
            'subject_area' => 'transactions',
            'keywords' => ['informasi dan transaksi elektronik', 'ite', 'transaksi elektronik', 'sistem elektronik', 'dokumen elektronik', 'tanda tangan digital']
        ],
        
        'digital_economy' => [
            'name' => 'Digital Economy & E-Commerce',
            'description' => 'Electronic commerce, digital business, and online trade',
            'subject_area' => 'economy',
            'keywords' => ['e-commerce', 'perdagangan elektronik', 'ekonomi digital', 'marketplace', 'fintech', 'financial technology', 'digital payment', 'pembayaran digital']
        ],
        
        'telecommunications' => [
            'name' => 'Telecommunications & Infrastructure',
            'description' => 'Telecommunications services and digital infrastructure',
            'subject_area' => 'infrastructure',
            'keywords' => ['telekomunikasi', 'infrastruktur digital', 'broadband', 'fiber optik', 'satelit', 'jaringan', 'spektrum frekuensi']
        ],
        
        'digital_innovation' => [
            'name' => 'Digital Innovation & Technology',
            'description' => 'Emerging technologies and innovation policies',
            'subject_area' => 'innovation',
            'keywords' => ['artificial intelligence', 'ai', 'kecerdasan buatan', 'blockchain', 'internet of things', 'iot', 'big data', 'cloud computing', 'startup', 'inovasi digital']
        ],
        
        'digital_identity' => [
            'name' => 'Digital Identity & Authentication',
            'description' => 'Digital identity systems and authentication mechanisms',
            'subject_area' => 'identity',
            'keywords' => ['identitas digital', 'digital identity', 'ktp elektronik', 'e-ktp', 'nik elektronik', 'autentikasi', 'authentication', 'sertifikat digital']
        ],
        
        'diplomatic_technology' => [
            'name' => 'Diplomatic Technology & International IT',
            'description' => 'Technology regulations for diplomatic and international relations',
            'subject_area' => 'diplomacy',
            'keywords' => ['diplomatik', 'kementerian luar negeri', 'kemlu', 'sistem informasi diplomatik', 'komunikasi diplomatik', 'cyber diplomacy']
        ],
        
        // Special Categories
        'amendment' => [
            'name' => 'Amendment',
            'description' => 'Amendments and modifications to existing regulations',
            'special_type' => 'modification',
            'keywords' => ['perubahan atas', 'amendment', 'revisi', 'perubahan', 'pencabutan']
        ],
        
        'implementation' => [
            'name' => 'Implementation',
            'description' => 'Implementation guidelines and technical regulations',
            'special_type' => 'procedural',
            'keywords' => ['pelaksanaan', 'penyelenggaraan', 'implementation', 'petunjuk teknis', 'juknis', 'tata cara']
        ],
        
        'transitional' => [
            'name' => 'Transitional',
            'description' => 'Temporary and transitional regulations',
            'special_type' => 'temporary',
            'keywords' => ['peralihan', 'transitional', 'sementara', 'temporary', 'masa transisi']
        ]
    ];

    /**
     * Agency-specific category mappings
     */
    private static array $agencyCategories = [
        'kementerian komunikasi dan informatika' => 'telecommunications',
        'kominfo' => 'telecommunications',
        'kemenkominfo' => 'telecommunications',
        'badan siber dan sandi negara' => 'cybersecurity',
        'bssn' => 'cybersecurity',
        'kementerian luar negeri' => 'diplomatic_technology',
        'kemlu' => 'diplomatic_technology',
        'kemenpan rb' => 'digital_government',
        'kementerian pendayagunaan aparatur negara' => 'digital_government',
        'ojk' => 'digital_economy',
        'bank indonesia' => 'digital_economy',
        'kementerian keuangan' => 'digital_economy'
    ];

    /**
     * Document type hierarchy mapping
     */
    private static array $documentTypeHierarchy = [
        'undang-undang' => 'primary_law',
        'peraturan pemerintah' => 'government_regulation',
        'peraturan presiden' => 'presidential_regulation',
        'peraturan menteri' => 'ministerial_regulation',
        'peraturan daerah' => 'local_regulation'
    ];

    /**
     * Classify a legal document into canonical categories
     */
    public static function classifyDocument(LegalDocument $document): array
    {
        $title = strtolower($document->title ?? '');
        $content = strtolower($document->full_text ?? '');
        $documentType = strtolower($document->document_type ?? '');
        $agency = strtolower($document->metadata['agency'] ?? '');
        $metadata = $document->metadata ?? [];
        
        $combinedText = "$title $content";
        
        // Primary classification by hierarchy
        $hierarchyCategory = self::classifyByHierarchy($documentType, $title);
        
        // Secondary classification by subject matter
        $subjectCategories = self::classifyBySubjectMatter($combinedText, $agency);
        
        // Special category detection
        $specialCategories = self::detectSpecialCategories($combinedText);
        
        // Calculate confidence scores
        $classifications = [];
        
        // Add hierarchy classification
        if ($hierarchyCategory) {
            $classifications[] = [
                'category' => $hierarchyCategory,
                'type' => 'hierarchy',
                'confidence' => 0.9,
                'reasoning' => 'Document type classification'
            ];
        }
        
        // Add subject matter classifications
        foreach ($subjectCategories as $category => $score) {
            $classifications[] = [
                'category' => $category,
                'type' => 'subject_matter',
                'confidence' => min($score / 10, 1.0),
                'reasoning' => 'Content and keyword analysis'
            ];
        }
        
        // Add special classifications
        foreach ($specialCategories as $category => $score) {
            $classifications[] = [
                'category' => $category,
                'type' => 'special',
                'confidence' => min($score / 5, 1.0),
                'reasoning' => 'Special pattern detection'
            ];
        }
        
        // Sort by confidence
        usort($classifications, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return [
            'primary_category' => $classifications[0]['category'] ?? 'unknown',
            'all_classifications' => array_slice($classifications, 0, 5),
            'suggested_tags' => self::generateTags($classifications),
            'classification_metadata' => [
                'classified_at' => now()->toISOString(),
                'classifier_version' => '1.0',
                'tik_relevance' => TikTermsService::calculateTikScore($combinedText)
            ]
        ];
    }

    /**
     * Classify by document hierarchy
     */
    private static function classifyByHierarchy(string $documentType, string $title): ?string
    {
        // Direct mapping from document type
        foreach (self::$documentTypeHierarchy as $pattern => $category) {
            if (stripos($documentType, $pattern) !== false) {
                return $category;
            }
        }
        
        // Fallback to title analysis
        foreach (self::$documentTypeHierarchy as $pattern => $category) {
            if (stripos($title, $pattern) !== false) {
                return $category;
            }
        }
        
        return null;
    }

    /**
     * Classify by subject matter
     */
    private static function classifyBySubjectMatter(string $text, string $agency): array
    {
        $scores = [];
        
        // Keyword-based scoring
        foreach (self::$canonicalCategories as $categoryKey => $categoryData) {
            if (!isset($categoryData['subject_area'])) continue;
            
            $score = 0;
            foreach ($categoryData['keywords'] as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    $score += 1;
                }
            }
            
            if ($score > 0) {
                $scores[$categoryKey] = $score;
            }
        }
        
        // Agency-based scoring (bonus points)
        foreach (self::$agencyCategories as $agencyPattern => $categoryKey) {
            if (stripos($agency, $agencyPattern) !== false) {
                $scores[$categoryKey] = ($scores[$categoryKey] ?? 0) + 3;
            }
        }
        
        return $scores;
    }

    /**
     * Detect special categories
     */
    private static function detectSpecialCategories(string $text): array
    {
        $scores = [];
        
        $specialCategories = ['amendment', 'implementation', 'transitional'];
        
        foreach ($specialCategories as $categoryKey) {
            $categoryData = self::$canonicalCategories[$categoryKey];
            $score = 0;
            
            foreach ($categoryData['keywords'] as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    $score += 1;
                }
            }
            
            if ($score > 0) {
                $scores[$categoryKey] = $score;
            }
        }
        
        return $scores;
    }

    /**
     * Generate tags from classifications
     */
    private static function generateTags(array $classifications): array
    {
        $tags = [];
        
        foreach (array_slice($classifications, 0, 3) as $classification) {
            $categoryData = self::$canonicalCategories[$classification['category']];
            $tags[] = $categoryData['name'];
        }
        
        return array_unique($tags);
    }

    /**
     * Get all canonical categories
     */
    public static function getCanonicalCategories(): array
    {
        return self::$canonicalCategories;
    }

    /**
     * Get category by key
     */
    public static function getCategory(string $key): ?array
    {
        return self::$canonicalCategories[$key] ?? null;
    }

    /**
     * Get categories by type
     */
    public static function getCategoriesByType(string $type): array
    {
        $categories = [];
        
        foreach (self::$canonicalCategories as $key => $data) {
            if (isset($data['hierarchy_level']) && $type === 'hierarchy') {
                $categories[$key] = $data;
            } elseif (isset($data['subject_area']) && $type === 'subject') {
                $categories[$key] = $data;
            } elseif (isset($data['special_type']) && $type === 'special') {
                $categories[$key] = $data;
            }
        }
        
        return $categories;
    }

    /**
     * Validate category key
     */
    public static function isValidCategory(string $key): bool
    {
        return isset(self::$canonicalCategories[$key]);
    }
}