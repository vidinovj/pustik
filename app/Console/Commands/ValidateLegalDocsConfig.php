<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ValidateLegalDocsConfig extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'legal-docs:validate-config {--test-api : Test API connectivity}';

    /**
     * The console command description.
     */
    protected $description = 'Validate legal documents configuration and test connectivity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Validating Legal Documents Configuration...');
        $this->newLine();

        $errors = [];
        $warnings = [];

        // Check required environment variables
        $this->info('✅ Checking Environment Variables:');
        $requiredEnvVars = [
            'JDIH_PERPUSNAS_BEARER_TOKEN' => 'JDIH Perpusnas API Token',
            'JDIH_PERPUSNAS_X_API_KEY' => 'JDIH Perpusnas X-API-Key',
            'JDIH_PERPUSNAS_BASE_URL' => 'JDIH Perpusnas Base URL',
        ];

        foreach ($requiredEnvVars as $var => $description) {
            $value = env($var);
            if (empty($value)) {
                $errors[] = "Missing required environment variable: {$var} ({$description})";
                $this->error("   ❌ {$var}: Missing");
            } else {
                $this->info("   ✅ {$var}: Set");
            }
        }

        // Check configuration values
        $this->newLine();
        $this->info('✅ Checking Configuration Values:');
        
        $config = config('legal_documents');
        if (empty($config)) {
            $errors[] = 'Legal documents configuration not found. Run: php artisan vendor:publish --tag=legal-documents-config';
            $this->error('   ❌ Configuration file missing');
        } else {
            $this->info('   ✅ Configuration file loaded');
            
            // Check cache configuration
            if (Cache::getStore() instanceof \Illuminate\Cache\NullStore) {
                $warnings[] = 'Cache is set to null driver. Consider using Redis or file cache for better performance.';
                $this->warn('   ⚠️  Cache driver is null - consider upgrading');
            } else {
                $this->info('   ✅ Cache driver configured');
            }
            
            // Check queue configuration
            $queueConnection = config('legal_documents.processing.queue_connection');
            if ($queueConnection === 'sync') {
                $warnings[] = 'Queue is set to sync driver. Consider using database or Redis for better performance.';
                $this->warn('   ⚠️  Queue driver is sync - consider upgrading');
            } else {
                $this->info('   ✅ Queue driver configured');
            }
        }

        // Test API connectivity if requested
        if ($this->option('test-api') && empty($errors)) {
            $this->newLine();
            $this->info('🌐 Testing API Connectivity:');
            
            try {
                $this->info('   Testing JDIH Perpusnas API endpoints...');
                
                // Test articles endpoint
                $response = Http::jdihPerpusnas()->get('/list-artikel?limit=1');
                
                if ($response->successful()) {
                    $this->info('   ✅ JDIH Perpusnas API: Articles endpoint working');
                } else {
                    $warnings[] = "JDIH Perpusnas API articles endpoint returned status: {$response->status()}";
                    $this->warn("   ⚠️  Articles endpoint: Status {$response->status()}");
                }
                
                // Test token generation endpoint
                $tokenResponse = Http::jdihPerpusnas()->get('/token');
                if ($tokenResponse->successful()) {
                    $this->info('   ✅ JDIH Perpusnas API: Token endpoint accessible');
                } else {
                    $this->warn("   ⚠️  Token endpoint: Status {$tokenResponse->status()}");
                }
                
            } catch (\Exception $e) {
                $errors[] = "Failed to connect to JDIH Perpusnas API: {$e->getMessage()}";
                $this->error('   ❌ JDIH Perpusnas API: Connection failed');
                $this->error("      Error: {$e->getMessage()}");
            }
        }

        // Summary
        $this->newLine();
        $this->info('📋 Validation Summary:');
        
        if (empty($errors) && empty($warnings)) {
            $this->info('🎉 All checks passed! Configuration is ready.');
            return Command::SUCCESS;
        }

        if (!empty($warnings)) {
            $this->newLine();
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->warn("   • {$warning}");
            }
        }

        if (!empty($errors)) {
            $this->newLine();
            $this->error('❌ Errors found:');
            foreach ($errors as $error) {
                $this->error("   • {$error}");
            }
            
            $this->newLine();
            $this->info('📝 Next steps:');
            $this->info('   1. Add missing environment variables to your .env file');
            $this->info('   2. Publish configuration: php artisan vendor:publish --tag=legal-documents-config');
            $this->info('   3. Test again: php artisan legal-docs:validate-config --test-api');
            
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}