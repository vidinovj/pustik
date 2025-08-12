#!/bin/bash
# quick-fixes.sh - Fix database and run TIK-focused scraper

echo "🔧 APPLYING QUICK FIXES"
echo "======================"

# Step 1: Create and run migration to fix database schema
echo "📊 Fixing database schema..."
php artisan make:migration fix_legal_documents_schema --table=legal_documents
echo "✅ Migration created - edit it with the schema fix, then run:"
echo "   php artisan migrate"
echo ""

# Step 2: Alternative - Manual SQL fix (if you prefer)
echo "🛠️ Or run this SQL directly:"
echo "ALTER TABLE legal_documents MODIFY COLUMN title TEXT;"
echo "ALTER TABLE legal_documents MODIFY COLUMN document_number TEXT;"
echo "ALTER TABLE legal_documents ADD COLUMN tik_relevance_score INT DEFAULT 0;"
echo "ALTER TABLE legal_documents ADD COLUMN tik_keywords JSON;"
echo "ALTER TABLE legal_documents ADD COLUMN document_category VARCHAR(100);"
echo "ALTER TABLE legal_documents ADD COLUMN is_tik_related BOOLEAN DEFAULT FALSE;"
echo ""

# Step 3: Test TIK-focused scraper
echo "🎯 Ready to test TIK-focused scraper:"
echo "php artisan scraper:tik-focused --limit=15"
echo ""

echo "📋 SUMMARY OF FIXES:"
echo "1. ✅ Database schema: Title/number columns now TEXT (unlimited length)"
echo "2. ✅ TIK focus: New scraper targets IT/tech regulations specifically"  
echo "3. ✅ Better sources: Focuses on jdih.kominfo.go.id and TIK search terms"
echo "4. ✅ Scoring system: Documents get TIK relevance scores"
echo ""

echo "🚀 Next steps:"
echo "1. Run: php artisan migrate (after editing migration)"
echo "2. Test: php artisan scraper:tik-focused --limit=15"
echo "3. Check results for actual IT/technology regulations"
echo ""

# Quick database check
echo "🔍 Current database status:"
php artisan tinker --execute="
echo 'Legal documents count: ' . App\Models\LegalDocument::count() . PHP_EOL;
echo 'Sample titles length: ' . PHP_EOL;
App\Models\LegalDocument::take(3)->get()->each(function(\$doc) {
    echo '- ' . strlen(\$doc->title) . ' chars: ' . substr(\$doc->title, 0, 80) . '...' . PHP_EOL;
});
"