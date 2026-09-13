<?php

namespace App\Http\Controllers\Admin;

use App\AI\Services\ProviderResolver;
use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function index(): View
    {
        $providers = AiProvider::orderBy('priority')->get();

        return view('admin.providers.index', compact('providers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'unique:ai_providers,slug'],
            'provider_type' => ['required', 'string', 'in:' . implode(',', array_keys(config('ai.provider_classes')))],
            'api_key' => ['nullable', 'string'],
            'api_base_url' => ['nullable', 'url'],
            'model' => ['nullable', 'string'],
            'priority' => ['required', 'integer', 'min:1'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
            'monthly_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        AiProvider::create($data);

        return back()->with('status', 'Provider added.');
    }

    public function update(Request $request, AiProvider $provider): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'api_key' => ['nullable', 'string'],
            'api_base_url' => ['nullable', 'url'],
            'model' => ['nullable', 'string'],
            'priority' => ['required', 'integer', 'min:1'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
            'monthly_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        // Keep existing encrypted key if a blank field was submitted.
        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        $provider->update($data);

        return back()->with('status', 'Provider updated.');
    }

    public function toggle(AiProvider $provider): RedirectResponse
    {
        $provider->update(['is_active' => ! $provider->is_active]);

        return back()->with('status', $provider->is_active ? 'Provider enabled.' : 'Provider disabled.');
    }

    public function resetUsage(AiProvider $provider): RedirectResponse
    {
        $provider->update(['used_today' => 0, 'used_this_month' => 0, 'failure_count' => 0, 'status' => 'healthy']);

        return back()->with('status', 'Usage counters reset.');
    }

    public function test(AiProvider $provider, ProviderResolver $resolver): JsonResponse
    {
        $instance = $resolver->resolve($provider);

        if (! $instance) {
            return response()->json(['ok' => false, 'message' => 'No provider class registered for this type.']);
        }

        $status = $instance->getStatus();

        return response()->json(['ok' => $status->value === 'healthy', 'status' => $status->value]);
    }

    public function logs(AiProvider $provider): View
    {
        $logs = AiUsageLog::where('provider_id', $provider->id)->latest('created_at')->paginate(25);

        return view('admin.providers.logs', compact('provider', 'logs'));
    }
}
