<?php
// app/Console/Commands/SimpleBrowserTest.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SimpleBrowserTest extends Command
{
    protected $signature = 'legal-docs:simple-browser-test';
    protected $description = 'Simple browser test with shorter timeout';

    public function handle(): int
    {
        $this->info('🚀 Simple Browser Test');
        
        $scriptContent = <<<'EOD'
const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({ 
        headless: false,
        devtools: true,
        defaultViewport: null
    });
    
    const page = await browser.newPage();
    
    await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    console.log('🌐 Navigating to document...');
    
    try {
        await page.goto('https://peraturan.go.id/id/permenpar-no-4-tahun-2025', { 
            waitUntil: 'domcontentloaded',
            timeout: 15000 
        });
        
        console.log('✅ Page loaded, checking content...');
        
        // Wait a bit for any dynamic content
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        const title = await page.title();
        const url = page.url();
        
        console.log('📄 Page Title: ' + title);
        console.log('🔗 Current URL: ' + url);
        
        // Check for login indicators
        const loginCheck = await page.evaluate(() => {
            const bodyText = document.body.innerText.toLowerCase();
            const title = document.title.toLowerCase();
            
            const loginIndicators = ['login', 'username', 'password', 'sign in', 'masuk'];
            const foundIndicators = [];
            
            for (const indicator of loginIndicators) {
                if (bodyText.includes(indicator) || title.includes(indicator)) {
                    foundIndicators.push(indicator);
                }
            }
            
            return {
                isLoginPage: foundIndicators.length > 0,
                indicators: foundIndicators,
                hasContent: bodyText.length > 1000
            };
        });
        
        if (loginCheck.isLoginPage) {
            console.log('❌ LOGIN PAGE DETECTED');
            console.log('   Indicators found: ' + loginCheck.indicators.join(', '));
        } else {
            console.log('✅ DOCUMENT PAGE ACCESSIBLE!');
            
            // Try to extract document title
            const docTitle = await page.evaluate(() => {
                const selectors = ['h1', 'h2', '.title', '.document-title', '[class*="judul"]'];
                for (const selector of selectors) {
                    const element = document.querySelector(selector);
                    if (element && element.textContent.trim().length > 15) {
                        return element.textContent.trim();
                    }
                }
                return null;
            });
            
            if (docTitle) {
                console.log('📋 Document Title: ' + docTitle);
            }
            
            // Check for actual content
            const contentCheck = await page.evaluate(() => {
                const contentSelectors = ['.content', 'main', 'article', '[class*="content"]'];
                let bestContent = '';
                
                for (const selector of contentSelectors) {
                    const element = document.querySelector(selector);
                    if (element) {
                        const text = element.textContent.trim();
                        if (text.length > bestContent.length) {
                            bestContent = text;
                        }
                    }
                }
                
                return {
                    hasContent: bestContent.length > 500,
                    contentLength: bestContent.length,
                    preview: bestContent.substring(0, 200)
                };
            });
            
            console.log('📊 Content Check:');
            console.log('   Has substantial content: ' + contentCheck.hasContent);
            console.log('   Content length: ' + contentCheck.contentLength + ' characters');
            if (contentCheck.preview) {
                console.log('   Preview: ' + contentCheck.preview + '...');
            }
        }
        
        console.log('🔍 Ready for manual inspection. Press Ctrl+C to close browser.');
        
        // Keep browser open for manual inspection
        await new Promise(resolve => setTimeout(resolve, 30000));
        
    } catch (error) {
        console.log('❌ Error: ' + error.message);
    } finally {
        await browser.close();
        console.log('🏁 Browser closed');
    }
})();
EOD;

        $scriptPath = storage_path('app/simple_test.cjs');
        file_put_contents($scriptPath, $scriptContent);
        
        try {
            $this->info('Opening browser for 30 seconds...');
            $this->line('You can manually inspect the page while it runs.');
            $this->newLine();
            
            $process = new Process(['node', $scriptPath]);
            $process->setTimeout(45); // Increased timeout
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });
            
            if ($process->getErrorOutput()) {
                $this->error('Errors:');
                $this->line($process->getErrorOutput());
            }
            
        } finally {
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
        
        $this->newLine();
        $this->info('💡 Based on what you saw:');
        $this->line('  • If LOGIN page → Site requires authentication');
        $this->line('  • If DOCUMENT accessible → Browser automation works!');
        $this->line('  • If content found → Ready to build full scraper');
        
        return Command::SUCCESS;
    }
}