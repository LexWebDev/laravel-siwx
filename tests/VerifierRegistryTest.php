<?php

use LexWebDev\Siwx\Contracts\SignatureVerifier;
use LexWebDev\Siwx\Exceptions\SiwxException;
use LexWebDev\Siwx\SiwxMessage;
use LexWebDev\Siwx\VerifierRegistry;
use LexWebDev\Siwx\Verifiers\Eip155Verifier;
use LexWebDev\Siwx\Verifiers\SolanaVerifier;

it('resolves the built-in verifiers', function () {
    $registry = app(VerifierRegistry::class);

    expect($registry->for('eip155'))->toBeInstanceOf(Eip155Verifier::class)
        ->and($registry->for('solana'))->toBeInstanceOf(SolanaVerifier::class);
});

it('rejects an unknown namespace', function () {
    app(VerifierRegistry::class)->for('bip122');
})->throws(SiwxException::class);

it('rejects a namespace disabled in config', function () {
    config()->set('siwx.namespaces', ['eip155']);

    app(VerifierRegistry::class)->for('solana');
})->throws(SiwxException::class);

it('accepts a custom verifier', function () {
    config()->set('siwx.namespaces', ['eip155', 'solana', 'bip122']);

    $registry = app(VerifierRegistry::class);

    $registry->register(new class implements SignatureVerifier {
        public function namespace(): string
        {
            return 'bip122';
        }

        public function verify(SiwxMessage $message, string $signature): bool
        {
            return true;
        }

        public function normaliseAddress(string $address): string
        {
            return $address;
        }
    });

    expect($registry->for('bip122')->namespace())->toBe('bip122');
});
