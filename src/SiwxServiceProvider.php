<?php

namespace LexWebDev\Siwx;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;
use LexWebDev\Siwx\Contracts\NonceRepository;
use LexWebDev\Siwx\Verifiers\Eip155Verifier;
use LexWebDev\Siwx\Verifiers\SolanaVerifier;

class SiwxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/siwx.php', 'siwx');

        $this->app->singleton(VerifierRegistry::class, fn ($app) => new VerifierRegistry(
            [new Eip155Verifier, new SolanaVerifier],
            (array) $app['config']->get('siwx.namespaces'),
        ));

        $this->app->singleton(NonceRepository::class, fn ($app) => new CacheNonceRepository(
            $app->make(CacheFactory::class),
            $app['config']->get('siwx.cache_store'),
            (int) $app['config']->get('siwx.nonce_ttl'),
        ));

        $this->app->singleton(SiwxVerifier::class, fn ($app) => new SiwxVerifier(
            new SiwxParser,
            $app->make(VerifierRegistry::class),
            $app->make(NonceRepository::class),
            (array) $app['config']->get('siwx.domains'),
            (int) $app['config']->get('siwx.clock_skew'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/siwx.php' => config_path('siwx.php'),
            ], 'siwx-config');
        }
    }
}
