<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'huggingface-primary' => ['lyrics'],
            'replicate-secondary' => ['music'],
            'stability-tertiary' => ['music'],
            'local-fallback' => ['lyrics', 'music', 'vocals', 'full_song'],
        ];

        foreach ($map as $slug => $capabilities) {
            $provider = AiProvider::where('slug', $slug)->first();

            if (! $provider) {
                continue;
            }

            foreach ($capabilities as $capability) {
                AiModel::updateOrCreate(
                    ['provider_id' => $provider->id, 'capability' => $capability],
                    ['name' => $provider->model, 'is_active' => true]
                );
            }
        }
    }
}
