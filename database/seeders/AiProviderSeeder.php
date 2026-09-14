<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Hugging Face Music',
                'slug' => 'huggingface-primary',
                'provider_type' => 'huggingface',
                'api_key' => env('AI_PROVIDER_A_KEY'),
                'api_base_url' => env('AI_PROVIDER_A_URL', 'https://api-inference.huggingface.co'),
                'model' => env('AI_PROVIDER_A_MODEL', 'facebook/musicgen-small'),
                'is_active' => true,
                'status' => 'healthy',
                'priority' => 1,
                'daily_limit' => 100,
                'monthly_limit' => 3000,
            ],
            [
                'name' => 'Replicate Music',
                'slug' => 'replicate-secondary',
                'provider_type' => 'replicate',
                'api_key' => env('AI_PROVIDER_B_KEY'),
                'api_base_url' => env('AI_PROVIDER_B_URL', 'https://api.replicate.com/v1'),
                'model' => env('AI_PROVIDER_B_MODEL', 'meta/musicgen'),
                'is_active' => true,
                'status' => 'healthy',
                'priority' => 2,
                'daily_limit' => 50,
                'monthly_limit' => 1000,
            ],
            [
                'name' => 'Stability Audio',
                'slug' => 'stability-tertiary',
                'provider_type' => 'stability',
                'api_key' => env('AI_PROVIDER_C_KEY'),
                'api_base_url' => env('AI_PROVIDER_C_URL', 'https://api.stability.ai/v2beta'),
                'model' => env('AI_PROVIDER_C_MODEL', 'stable-audio-2'),
                'is_active' => true,
                'status' => 'healthy',
                'priority' => 3,
                'daily_limit' => 50,
                'monthly_limit' => 1000,
            ],
            [
                'name' => 'Local Fallback',
                'slug' => 'local-fallback',
                'provider_type' => 'local',
                'api_key' => null,
                'api_base_url' => env('AI_LOCAL_MODEL_URL', 'http://127.0.0.1:8800'),
                'model' => 'local-open-source',
                'is_active' => true,
                'status' => 'healthy',
                'priority' => 4,
                'daily_limit' => null,
                'monthly_limit' => null,
            ],
        ];

        foreach ($providers as $provider) {
            AiProvider::query()->updateOrCreate(
                ['slug' => $provider['slug']],
                $provider,
            );
        }
    }
}
