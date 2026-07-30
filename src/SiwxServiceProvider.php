<?php

namespace LexWebDev\Siwx;

use Illuminate\Support\ServiceProvider;
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
