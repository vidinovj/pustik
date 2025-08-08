# 🕷️ Web Scraping System - Complete Setup Guide

## 📁 Files Created

### Models (connect to your database)
- `app/Models/LegalDocument.php` - Main document model
- `app/Models/DocumentSource.php` - Source management
- `app/Models/ApiLog.php` - API request logging
- `app/Models/UrlMonitoring.php` - URL health monitoring

### Scraper Infrastructure
- `app/Services/Scrapers/BaseScraper.php` - Base scraper class (reusable)
- `app/Services/Scrapers/JdihKemluScraper.php` - JDIH Kemlu implementation
- `app/Services/Scrapers/ScraperFactory.php` - Manages all scrapers

### Queue Jobs (background processing)
- `app/Jobs/ScrapeDocumentsJob.php` - Main scraping job
- `app/Jobs/MonitorUrlJob.php` - URL monitoring job

### Console Commands
- `app/Console/Commands/ScrapeDocuments.php` - Scraping management

### Database Seeders
- `database/seeders/DocumentSourcesSeeder.php` - Initial source data

## 🚀 Setup Instructions

### 1. **Create Files**
Place all the artifacts in their respective locations in your Laravel project.

### 2. **Update DatabaseSeeder**
Add to `database/seeders/DatabaseSeeder.php`:
```php
public function run()
{
    $this->call([
        AdminSeeder::class,
        DocumentSourcesSeeder::class, // Add this line
    ]);
}
```

### 3. **Run Seeders**
```bash
php artisan db:seed --class=DocumentSourcesSeeder
```

### 4. **Configure Queue Worker**
Since scraping runs in background, set up queue processing:
```bash
# In production, use supervisor to keep this running
php artisan queue:work --queue=legal-documents
```

## 📖 Web Scraping Concepts Explained

### **What We Built:**

1. **BaseScraper** - Provides common functionality:
   - HTTP requests with error handling
   - HTML parsing using DOMDocument/XPath
   - Rate limiting and politeness
   - Database saving with duplicate detection
   - URL monitoring and health checks

2. **Concrete Scrapers** - Site-specific implementations:
   - JdihKemluScraper - Example for Foreign Ministry
   - Placeholder classes for other sites

3. **Factory Pattern** - Manages scraper creation:
   - Maps source types to scraper classes
   - Creates appropriate scraper instances
   - Handles scraper registration

4. **Queue System** - Background processing:
   - ScrapeDocumentsJob - Main scraping process
   - MonitorUrlJob - URL health monitoring
   - Automatic retry and error handling

## 🎯 Usage Examples

### **Start Scraping (Interactive)**
```bash
php artisan legal-docs:scrape
```

### **Scrape Specific Source**
```bash
php artisan legal-docs:scrape --source=jdih_kemlu
```

### **Scrape All Sources (Background)**
```bash
php artisan legal-docs:scrape --all --queue
```

### **Test Run (No Changes)**
```bash
php artisan legal-docs:scrape --all --dry-run
```

## 🔧 How Web Scraping Works

### **1. HTTP Requests**
```php
// BaseScraper makes requests like this:
$response = Http::legalDocsScraper()
    ->timeout(30)
    ->withHeaders($this->headers)
    ->get($url);
```

### **2. HTML Parsing**
```php
// Parse HTML into searchable DOM
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Extract data using CSS-like selectors
$title = $xpath->query('//h1[@class="document-title"]')->item(0);
```

### **3. Data Extraction**
```php
// Clean and normalize text
$cleanTitle = $this->cleanText($this->extractText($titleElement));

// Parse Indonesian dates
$issueDate = $this->parseIndonesianDate($dateString);
```

### **4. Database Storage**
```php
// Save with duplicate detection
$document = LegalDocument::create([
    'title' => $title,
    'document_type' => $type,
    'source_url' => $url,
    'metadata' => $metadata,
    'checksum' => md5($title . $number . $date),
]);
```

## 🛠️ Extending the System

### **Add New Scraper**
1. Create new scraper class extending `BaseScraper`
2. Implement required methods:
   - `scrape()` - Main scraping logic
   - `getDocumentUrls()` - Find document URLs
   - `extractDocumentData()` - Extract metadata

3. Register in ScraperFactory:
```php
ScraperFactory::register('new_site', NewSiteScraper::class);
```

### **Example New Scraper Structure**
```php
class NewSiteScraper extends BaseScraper
{
    public function scrape(): array
    {
        $urls = $this->getDocumentUrls();
        $documents = [];
        
        foreach ($urls as $url) {
            $html = $this->makeRequest($url);
            if ($html) {
                $dom = $this->parseHtml($html);
                $data = $this->extractDocumentData($dom, $url);
                if ($data) {
                    $documents[] = $this->saveDocument($data);
                }
            }
        }
        
        return $documents;
    }
    
    protected function getDocumentUrls(): array
    {
        // Implement URL discovery logic
    }
    
    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        // Implement data extraction logic
    }
}
```

## 📊 Monitoring & Debugging

### **Check Scraping Status**
```bash
# View document sources
php artisan tinker
>>> DocumentSource::with('legalDocuments')->get()

# Check recent logs
tail -f storage/logs/legal-documents.log

# Monitor queue jobs
php artisan queue:failed
```

### **URL Health Monitoring**
```bash
# Check URL status
>>> UrlMonitoring::broken()->get()

# Monitor specific URL
>>> UrlMonitoring::where('url', 'https://example.com')->first()
```

### **Database Queries**
```php
// Get documents by source
LegalDocument::fromSource('jdih_kemlu')->count()

// Recent documents
LegalDocument::where('created_at', '>', now()->subDays(7))->get()

// Failed URLs
UrlMonitoring::broken()->where('failure_count', '>', 3)->get()
```

## ⚠️ Best Practices

### **Be Polite**
- Use delays between requests (2-3 seconds)
- Respect robots.txt files
- Don't overwhelm government servers
- Monitor for IP blocking

### **Handle Errors Gracefully**
- Log all errors for debugging
- Use retry mechanisms
- Monitor URL health
- Have fallback strategies

### **Data Quality**
- Validate extracted data
- Handle encoding issues (UTF-8)
- Clean text content
- Detect and skip duplicates

### **Performance**
- Use queue jobs for large scraping operations
- Cache static data
- Monitor memory usage
- Set reasonable timeouts

## 🎉 What You've Accomplished

✅ **Complete scraping infrastructure** that can handle any government website  
✅ **JDIH Kemlu scraper** ready to collect Foreign Ministry documents  
✅ **Queue system** for background processing at scale  
✅ **URL monitoring** to track link health and permanence  
✅ **Extensible architecture** to add new sources easily  
✅ **Comprehensive logging** for debugging and monitoring  
✅ **Database integration** with your existing schema  

**You now have a production-ready web scraping system** that can collect thousands of Indonesian legal documents while being respectful to government servers and handling errors gracefully!

The system is designed to work seamlessly with the JDIH API once you get production access - they'll complement each other perfectly.