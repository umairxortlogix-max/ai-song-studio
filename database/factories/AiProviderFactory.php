<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AiProviderFactory extends Factory
{
    protected $model = \App\Models\AiProvider::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' AI',
            'slug' => fake()->unique()->slug(),
            'provider_type' => 'fake',
            'api_key' => 'test-key',
            'api_base_url' => 'https://example.test',
            'model' => 'test-model',
            'is_active' => true,
            'priority' => fake()->numberBetween(1, 10),
            'daily_limit' => 100,
            'monthly_limit' => 3000,
            'used_today' => 0,
            'used_this_month' => 0,
            'status' => 'healthy',
        ];
    }
}
