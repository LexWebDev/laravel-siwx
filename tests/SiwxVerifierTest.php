<?php

use Illuminate\Support\Facades\Cache;
use LexWebDev\Siwx\Exceptions\SiwxException;
use LexWebDev\Siwx\SiwxVerifier;

beforeEach(function () {
    // Векторы подписаны с Issued At 2026-07-30T12:34:56Z. Без фиксации времени
    // тесты зависели бы от того, наступил ли этот момент, а не от кода.
    $this->travelTo('2026-07-30T12:40:00Z');

    config()->set('siwx.domains', ['dapp.expert']);
    Cache::put('siwx:nonce:8Kc2fQ1pXm9Zr4Lt', true, 600);
});

it('verifies an evm message', function () {
    $session = app(SiwxVerifier::class)->verify(EVM_MESSAGE, EVM_SIG);

    expect($session->address)->toBe(strtolower(EVM_SIGNER))
        ->and($session->namespace)->toBe('eip155')
        ->and($session->chainId)->toBe('eip155:1')
        ->and($session->domain)->toBe('dapp.expert');
});

it('verifies a one-click auth message with resources', function () {
    $session = app(SiwxVerifier::class)->verify(EVM_RESOURCES_MESSAGE, EVM_RESOURCES_SIG);

    expect($session->address)->toBe(strtolower(EVM_SIGNER));
});

it('verifies a solana message and keeps address casing', function () {
    $session = app(SiwxVerifier::class)->verify(SOLANA_MESSAGE, SOLANA_SIG_BASE64);

    expect($session->address)->toBe(SOLANA_SIGNER)
        ->and($session->namespace)->toBe('solana');
});

it('burns the nonce so a replay fails', function () {
    app(SiwxVerifier::class)->verify(EVM_MESSAGE, EVM_SIG);

    expect(fn () => app(SiwxVerifier::class)->verify(EVM_MESSAGE, EVM_SIG))
        ->toThrow(SiwxException::class);
});

it('keeps the nonce when the signature is wrong', function () {
    try {
        app(SiwxVerifier::class)->verify(EVM_MESSAGE, EVM_FOREIGN_SIG);
    } catch (SiwxException) {
        // ожидаемо
    }

    expect(app(SiwxVerifier::class)->verify(EVM_MESSAGE, EVM_SIG)->address)
        ->toBe(strtolower(EVM_SIGNER));
});

it('rejects a domain outside the allow list', function () {
    config()->set('siwx.domains', ['other.example']);

    expect(fn () => app(SiwxVerifier::class)->verify(EVM_MESSAGE, EVM_SIG))
        ->toThrow(SiwxException::class);
});

it('rejects an empty allow list', function () {
    config()->set('siwx.domains', []);

    expect(fn () => app(SiwxVerifier::class)->verify(EVM_MESSAGE, EVM_SIG))
        ->toThrow(SiwxException::class);
});

it('rejects a nonce that was never issued', function () {
    Cache::flush();

    expect(fn () => app(SiwxVerifier::class)->verify(EVM_MESSAGE, EVM_SIG))
        ->toThrow(SiwxException::class);
});

it('rejects an expired message', function () {
    $expired = EVM_MESSAGE . "\nExpiration Time: 2026-07-30T12:00:00.000Z";

    expect(fn () => app(SiwxVerifier::class)->verify($expired, EVM_SIG))
        ->toThrow(SiwxException::class);
});

it('rejects a disabled namespace', function () {
    config()->set('siwx.namespaces', ['eip155']);

    expect(fn () => app(SiwxVerifier::class)->verify(SOLANA_MESSAGE, SOLANA_SIG_BASE64))
        ->toThrow(SiwxException::class);
});
