<?php

namespace Tests\Feature;

use App\AI\DTOs\LyricsRequest;
use App\AI\DTOs\LyricsResult;
use App\AI\Exceptions\AllProvidersUnavailableException;
use App\AI\Exceptions\QuotaExceededException;
use App\AI\Exceptions\RateLimitException;
use App\AI\Services\AiRouterService;
use App\AI\Services\ProviderResolver;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * These tests are the core proof of the "most important feature":
 * Provider A fails -> Provider B automatically starts -> generation
 * continues without the user restarting the request.
 */
class AiRouterFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_next_provider_on_rate_limit(): void
    {
        $providerA = AiProvider::factory()->create(['priority' => 1, 'slug' => 'provider-a', 'provider_type' => 'fake']);
        $providerB = AiProvider::factory()->create(['priority' => 2, 'slug' => 'provider-b', 'provider_type' => 'fake']);

        $fakeA = Mockery::mock(\App\AI\Contracts\MusicProviderInterface::class);
        $fakeA->shouldReceive('generateLyrics')->once()->andThrow(new RateLimitException('provider-a'));
        $fakeA->shouldReceive('supportedOperations')->andReturn(['generate_lyrics']);

        $fakeB = Mockery::mock(\App\AI\Contracts\MusicProviderInterface::class);
        $fakeB->shouldReceive('generateLyrics')->once()->andReturn(new LyricsResult('Generated lyrics', 'provider-b'));
        $fakeB->shouldReceive('supportedOperations')->andReturn(['generate_lyrics']);

        $resolver = Mockery::mock(ProviderResolver::class);
        $resolver->shouldReceive('resolve')->with(Mockery::on(fn ($p) => $p->id === $providerA->id))->andReturn($fakeA);
        $resolver->shouldReceive('resolve')->with(Mockery::on(fn ($p) => $p->id === $providerB->id))->andReturn($fakeB);

        $router = new AiRouterService($resolver);

        $result = $router->attempt('generate_lyrics', new LyricsRequest('theme', 'english', 'pop', 'happy'), null);

        $this->assertEquals('Generated lyrics', $result->lyrics);
        $this->assertEquals('provider-b', $result->providerSlug);

        $this->assertDatabaseHas('ai_usage_logs', ['provider_id' => $providerA->id, 'status' => 'failed']);
        $this->assertDatabaseHas('ai_usage_logs', ['provider_id' => $providerB->id, 'status' => 'success']);
    }

    public function test_it_skips_a_provider_whose_local_quota_is_already_exhausted(): void
    {
        $providerA = AiProvider::factory()->create([
            'priority' => 1, 'slug' => 'provider-a', 'provider_type' => 'fake',
            'daily_limit' => 10, 'used_today' => 10,
        ]);
        $providerB = AiProvider::factory()->create(['priority' => 2, 'slug' => 'provider-b', 'provider_type' => 'fake']);

        $fakeB = Mockery::mock(\App\AI\Contracts\MusicProviderInterface::class);
        $fakeB->shouldReceive('generateLyrics')->once()->andReturn(new LyricsResult('From B', 'provider-b'));
        $fakeB->shouldReceive('supportedOperations')->andReturn(['generate_lyrics']);

        $resolver = Mockery::mock(ProviderResolver::class);
        // provider A should never even be resolved because its quota is exhausted locally
        $resolver->shouldNotReceive('resolve')->with(Mockery::on(fn ($p) => $p->id === $providerA->id));
        $resolver->shouldReceive('resolve')->with(Mockery::on(fn ($p) => $p->id === $providerB->id))->andReturn($fakeB);

        $router = new AiRouterService($resolver);
        $result = $router->attempt('generate_lyrics', new LyricsRequest('theme', 'english', 'pop', 'happy'), null);

        $this->assertEquals('From B', $result->lyrics);
    }

    public function test_it_throws_when_all_providers_fail(): void
    {
        $providerA = AiProvider::factory()->create(['priority' => 1, 'slug' => 'provider-a', 'provider_type' => 'fake']);
        $providerB = AiProvider::factory()->create(['priority' => 2, 'slug' => 'provider-b', 'provider_type' => 'fake']);

        $fakeA = Mockery::mock(\App\AI\Contracts\MusicProviderInterface::class);
        $fakeA->shouldReceive('generateLyrics')->once()->andThrow(new QuotaExceededException('provider-a'));
        $fakeA->shouldReceive('supportedOperations')->andReturn(['generate_lyrics']);

        $fakeB = Mockery::mock(\App\AI\Contracts\MusicProviderInterface::class);
        $fakeB->shouldReceive('generateLyrics')->once()->andThrow(new QuotaExceededException('provider-b'));
        $fakeB->shouldReceive('supportedOperations')->andReturn(['generate_lyrics']);

        $resolver = Mockery::mock(ProviderResolver::class);
        $resolver->shouldReceive('resolve')->andReturn($fakeA, $fakeB);

        $router = new AiRouterService($resolver);

        $this->expectException(AllProvidersUnavailableException::class);
        $router->attempt('generate_lyrics', new LyricsRequest('theme', 'english', 'pop', 'happy'), null);
    }

    public function test_disabled_providers_are_never_attempted(): void
    {
        AiProvider::factory()->create(['priority' => 1, 'slug' => 'disabled-one', 'is_active' => false]);
        $active = AiProvider::factory()->create(['priority' => 2, 'slug' => 'active-one', 'provider_type' => 'fake']);

        $fake = Mockery::mock(\App\AI\Contracts\MusicProviderInterface::class);
        $fake->shouldReceive('generateLyrics')->once()->andReturn(new LyricsResult('ok', 'active-one'));
        $fake->shouldReceive('supportedOperations')->andReturn(['generate_lyrics']);

        $resolver = Mockery::mock(ProviderResolver::class);
        $resolver->shouldReceive('resolve')->once()->with(Mockery::on(fn ($p) => $p->slug === 'active-one'))->andReturn($fake);

        $router = new AiRouterService($resolver);
        $result = $router->attempt('generate_lyrics', new LyricsRequest('theme', 'english', 'pop', 'happy'), null);

        $this->assertEquals('ok', $result->lyrics);
    }
}
