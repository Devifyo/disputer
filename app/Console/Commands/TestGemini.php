<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestGemini extends Command
{
    protected $signature = 'gemini:test';
    protected $description = 'Test Gemini API connection and list models';

    public function handle()
    {
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            $this->error('❌ API Key missing in .env');
            return;
        }

        $this->info('Testing API Key...');

        // 1. Get List of Available Models
        $response = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");

        if ($response->failed()) {
            $this->error('❌ Connection Failed: ' . $response->status());
            $this->line($response->body());
            return;
        }

        $models = $response->json()['models'] ?? [];
        $this->info('✅ Connection Successful!');
        $this->info('Here are the models available to your key:');
        $this->newLine();

        $foundAny = false;
        foreach ($models as $model) {
            // Check if it supports content generation
            if (in_array('generateContent', $model['supportedGenerationMethods'])) {
                $this->comment("👉 " . $model['name']);
                $foundAny = true;
            }
        }

        if (!$foundAny) {
            $this->warn('⚠️ No models found that support content generation. You may need a new API key.');
        }
    }
}
