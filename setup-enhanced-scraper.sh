#!/bin/bash
# setup-enhanced-scraper.sh

echo " Setting up Enhanced Document Scraper"
echo "========================================"

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 16+ first."
    echo "   Visit: https://nodejs.org/"
    exit 1
fi

NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 16 ]; then
    echo "❌ Node.js version 16+ required. Current: $(node -v)"
    exit 1
fi

echo "✅ Node.js $(node -v) detected"

# Check if Python 3 is available (for Selenium option)
if command -v python3 &> /dev/null; then
    echo "✅ Python 3 detected: $(python3 --version)"
    PYTHON_AVAILABLE=true
else
    echo "⚠️  Python 3 not found - Selenium strategy will not work"
    PYTHON_AVAILABLE=false
fi

# Install Node.js dependencies
echo " Installing Node.js dependencies..."
npm install

if [ $? -ne 0 ]; then
    echo "❌ Failed to install Node.js dependencies"
    exit 1
fi

echo "✅ Node.js dependencies installed"

# Install Python dependencies if Python is available
if [ "$PYTHON_AVAILABLE" = true ]; then
    echo " Installing Python dependencies..."
    
    # Check if pip3 is available
    if command -v pip3 &> /dev/null; then
        pip3 install selenium beautifulsoup4 requests lxml
        echo "✅ Python dependencies installed"
    else
        echo "⚠️  pip3 not found - install manually: pip3 install selenium beautifulsoup4 requests lxml"
    fi
    
    # Check for Chrome/Chromium
    if command -v google-chrome &> /dev/null || command -v chromium-browser &> /dev/null; then
        echo "✅ Chrome/Chromium browser detected"
    else
        echo "⚠️  Chrome/Chromium not found - install for Selenium support"
        echo "   Ubuntu: sudo apt install chromium-browser"
        echo "   CentOS: sudo yum install chromium"
        echo "   macOS: brew install chromium"
    fi
fi

# Create necessary directories
echo " Creating directories..."
mkdir -p storage/app/scraper_tests
mkdir -p storage/app/temp
mkdir -p storage/logs

# Set permissions
chmod 755 storage/app/scraper_tests
chmod 755 storage/app/temp

echo "✅ Directories created"

# Create test configuration
echo "⚙️  Creating test configuration..."

cat > storage/app/scraper_config.json << 'EOF'
{
  "default_strategies": ["stealth", "basic", "mobile"],
  "retry_strategies": ["stealth", "selenium"],
  "delays": {
    "min": 3,
    "max": 8,
    "between_sites": 10
  },
  "timeouts": {
    "request": 45,
    "page_load": 60
  },
  "user_agents": {
    "rotate": true,
    "include_mobile": true
  },
  "test_urls": {
    "peraturan_go_id": [
      "https://peraturan.go.id/id/undang-undang-no-11-tahun-2008",
      "https://peraturan.go.id/id/peraturan-pemerintah-no-71-tahun-2019"
    ],
    "jdih_sites": [
      "https://jdih.kemlu.go.id/portal/detail-search/peraturan-menteri-luar-negeri-nomor-1-tahun-2020"
    ]
  }
}
EOF

echo "✅ Configuration created"

# Test basic functionality
echo " Running basic test..."

node -e "
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

(async () => {
  try {
    const browser = await puppeteer.launch({headless: true});
    const page = await browser.newPage();
    await page.goto('https://httpbin.org/user-agent');
    const content = await page.content();
    await browser.close();
    
    if (content.includes('user-agent')) {
      console.log('✅ Basic Puppeteer test passed');
    } else {
      console.log('❌ Basic Puppeteer test failed');
    }
  } catch (error) {
    console.log('❌ Basic test failed:', error.message);
  }
})();
"

echo ""
echo " Setup Complete!"
echo "=================="
echo ""
echo "Next steps:"
echo "1. Test the scraper: php artisan scraper:test-enhanced"
echo "2. Check results in storage/app/scraper_tests/"
echo "3. Adjust strategies based on success rates"
echo ""
echo "Available strategies:"
echo "- stealth: Uses puppeteer-extra with stealth plugin"
echo "- basic: Simple HTTP requests with headers"
echo "- mobile: Mobile user agent simulation"
echo "- selenium: Heavy-duty browser automation (if Python available)"
echo ""
echo "Troubleshooting:"
echo "- Check logs in storage/logs/"
echo "- Increase delays if getting blocked"
echo "- Use single strategy for debugging: --strategies=stealth"
echo ""

if [ "$PYTHON_AVAILABLE" = false ]; then
    echo "⚠️  To enable Selenium strategy:"
    echo "   1. Install Python 3: https://python.org"
    echo "   2. Install packages: pip3 install selenium beautifulsoup4 requests"
    echo "   3. Install Chrome/Chromium browser"
    echo ""
fi

echo "Happy scraping! ️"
