<?php
// app/Console/Commands/ManualBrowserCheck.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ManualBrowserCheck extends Command
{
    protected $signature = 'legal-docs:manual-check';
    protected $description = 'Open browser for manual inspection - no timeout issues';

    public function handle(): int
    {
        $this->info('🌐 Opening Browser for Manual Inspection');
        $this->newLine();
        
        $scriptContent = <<<'EOD'
const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({ 
        headless: false,
        devtools: true,
        defaultViewport: null,
        args: ['--start-maximized']
    });
    
    const page = await browser.newPage();
    
    await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    console.log('🌐 Navigating to test document...');
    
    try {
        await page.goto('https://peraturan.go.id/id/permenpar-no-4-tahun-2025', { 
            waitUntil: 'domcontentloaded',
            timeout: 15000 
        });
        
        console.log('✅ Page loaded successfully!');
        console.log('🔍 Inspect the page manually in the browser window.');
        console.log('');
        console.log('CHECK FOR:');
        console.log('  • Is this a login page or document page?');
        console.log('  • Can you see document title/content?');
        console.log('  • Is there a "Sign In" or "Login" form?');
        console.log('');
        console.log('When done inspecting, close this terminal (Ctrl+C) to close browser.');
        
        // Simple evaluation
        const pageInfo = await page.evaluate(() => {
            return {
                title: document.title,
                url: window.location.href,
                bodyText: document.body.innerText.substring(0, 500)
            };
        });
        
        console.log('📄 Page Title: ' + pageInfo.title);
        console.log('🔗 Current URL: ' + pageInfo.url);
        console.log('📝 Content Preview: ' + pageInfo.bodyText + '...');
        
        // Keep running until manually stopped
        await new Promise(() => {}); // Infinite wait
        
    } catch (error) {
        console.log('❌ Error: ' + error.message);
        await browser.close();
    }
})();
EOD;

        $scriptPath = storage_path('app/manual_check.cjs');
        file_put_contents($scriptPath, $scriptContent);
        
        $this->info('Instructions:');
        $this->line('1. Browser will open with the document page');
        $this->line('2. Manually inspect what you see');
        $this->line('3. Press Ctrl+C here when done to close browser');
        $this->newLine();
        
        try {
            $process = new Process(['node', $scriptPath]);
            $process->setTimeout(null); // No timeout
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });
            
        } catch (\Exception $e) {
            $this->line('Browser closed.');
        } finally {
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
        
        $this->newLine();
        $this->ask('What did you see in the browser? (login page / document page / error)');
        
        return Command::SUCCESS;
    }
}