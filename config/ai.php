<?php

use App\AI\Providers\HuggingFaceProvider;
use App\AI\Providers\LocalModelProvider;
use App\AI\Providers\ReplicateProvider;
use App\AI\Providers\StabilityAudioProvider;

return [

    /*
    |--------------------------------------------------------------------
    | Provider type -> concrete class map
    |--------------------------------------------------------------------
    |
    | The `ai_providers` DB table stores rows with a `provider_type`
    | column (e.g. "huggingface", "replicate", "stability", "local").
    | This map is the ONLY place in the whole app that names a concrete
    | provider class. To add a new provider:
    |
    |   1. Write a class implementing MusicProviderInterface.
    |   2. Add one line here mapping a provider_type string to it.
    |   3. Insert a row into ai_providers (via admin panel or seeder)
    |      with that provider_type, your API key, base URL, model,
    |      and a priority (lower number = tried first).
    |
    | No changes to AiRouterService, controllers, or jobs are ever
    | needed to add/remove/reorder providers.
    |
    */
    'provider_classes' => [
        'huggingface' => HuggingFaceProvider::class,
        'replicate' => ReplicateProvider::class,
        'stability' => StabilityAudioProvider::class,
        'local' => LocalModelProvider::class,
    ],

    /*
    |--------------------------------------------------------------------
    | Local / self-hosted model fallback server
    |--------------------------------------------------------------------
    */
    'local_model_url' => env('AI_LOCAL_MODEL_URL', 'http://127.0.0.1:8800'),

    /*
    |--------------------------------------------------------------------
    | Default per-user daily generation limit (independent of provider
    | quotas — this caps how many songs a single user can generate).
    |--------------------------------------------------------------------
    */
    'user_daily_generation_limit' => env('AI_USER_DAILY_LIMIT', 5),

];
