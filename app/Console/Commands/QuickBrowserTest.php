<?php
// app/Console/Commands/QuickBrowserTest.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class QuickBrowserTest extends Command
{
    protected $signature = 'legal-docs:quick-browser-test';
    protected $description = 'Quick browser test to verify document accessibility';

    public function handle(): int
    {
        $this->info('🚀 Quick Browser Test');
        
        $scriptContent = <<<JS
const puppeteer = require('puppeteer');

(async () => {
    try {
        const browser = await puppeteer.launch({ headless: false });
        const page = await browser.newPage();
        
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        console.log('🌐 Navigating to test document...');
        await page.goto('https://peraturan.go.id/id/permenpar-no-4-tahun-2025', { 
            waitUntil: 'networkidle2', 
            timeout: 30000 
        });
        
        await page.waitForTimeout(3000);
        
        const title = await page.title();
        const url = page.url();
        
        console.log('📄 Page Title:', title);
        console.log('🔗 Final URL:', url);
        
        const isLoginPage = title.toLowerCase().includes('login') || 
                           url.includes('login') ||
                           await page.evaluate(() => {
                               return document.body.innerText.toLowerCase().includes('username') ||
                                      document.body.innerText.toLowerCase().includes('password') ||
                                      document.body.innerText.toLowerCase().includes('sign in');
                           });
        
        if (isLoginPage) {
            console.log('❌ RESULT: Login page detected');
        } else {
            console.log('✅ RESULT: Document accessible!');
            
            // Try to find document title
            const docTitle = await page.evaluate(() => {
                const selectors = ['h1', 'h2', '.title', '.document-title'];
                for (const selector of selectors) {
                    const element = document.querySelector(selector);
                    if (element && element.textContent.trim().length > 15) {
                        return element.textContent.trim();
                    }
                }
                return null;
            });
            
            if (docTitle) {
                console.log('📋 Document Title:', docTitle);
            }
        }
        
        await browser.close();
        
    } catch (error) {
        console.error('❌ Error:', error.message);
    }
})();
JS;

        $scriptPath = storage_path('app/quick_test.cjs'); // Use .cjs extension
        file_put_contents($scriptPath, $scriptContent);
        
        try {
            $process = new Process(['node', $scriptPath]);
            $process->setTimeout(60);
            $process->run();
            
            $this->line($process->getOutput());
            
            if ($process->getErrorOutput()) {
                $this->error($process->getErrorOutput());
            }
            
        } finally {
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
        
        return Command::SUCCESS;
    }
}