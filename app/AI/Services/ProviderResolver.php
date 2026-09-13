<?php

namespace App\AI\Services;

use App\AI\Contracts\MusicProviderInterface;
use App\Models\AiProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Maps a DB row (App\Models\AiProvider) to a concrete class implementing
 * MusicProviderInterface, using the `provider_type` column and the
 * config('ai.provider_classes') map. This is the ONLY place that knows
 * about concrete provider class names — add a new provider by adding
 * one line to config/ai.php, no other code changes needed.
 */
class ProviderResolver
{
    public function resolve(AiProvider $providerModel): ?MusicProviderInterface
    {
        $map = config('ai.provider_classes', []);
        $class = $map[$providerModel->provider_type] ?? null;

        if (! $class || ! class_exists($class)) {
            Log::warning("ProviderResolver: no class mapped for provider_type [{$providerModel->provider_type}]");
            return null;
        }

        try {
            /** @var MusicProviderInterface $instance */
            $instance = App::make($class, ['providerModel' => $providerModel]);
            return $instance;
        } catch (\Throwable $e) {
            Log::error("ProviderResolver: failed to instantiate {$class}: {$e->getMessage()}");
            return null;
        }
    }
}
