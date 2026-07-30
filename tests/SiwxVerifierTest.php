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

it('accepts a domain that carries a port', function () {
    // Захват от настоящего кошелька: AppKit кладёт в domain window.location.host,
    // то есть localhost:3000 с портом, а в URI — origin, откуда parse_url() достаёт
    // localhost без порта. Одной записи в allow-list должно быть достаточно.
    $this->travelTo('2026-07-30T14:20:00Z');
    config()->set('siwx.domains', ['localhost:3000']);
    Cache::put('siwx:nonce:LZAsP6lEAHmRQuQ4', true, 600);

    $session = app(SiwxVerifier::class)->verify(PHANTOM_MESSAGE, PHANTOM_SIG);

    expect($session->address)->toBe(PHANTOM_SIGNER)
        ->and($session->domain)->toBe('localhost:3000');
});

it('rejects a uri whose host differs from the domain', function () {
    // Оба значения в allow-list, но URI указывает на чужой хост. Подпись при этом
    // не проверяется — отказ обязан наступить раньше, на разборе домена.
    config()->set('siwx.domains', ['dapp.expert', 'evil.example']);
    $raw = str_replace('URI: https://dapp.expert', 'URI: https://evil.example', EVM_MESSAGE);

    $code = null;

    try {
        app(SiwxVerifier::class)->verify($raw, EVM_SIG);
    } catch (SiwxException $exception) {
        $code = $exception->code();
    }

    expect($code)->toBe('siwx_invalid_domain');
});

it('rejects a uri whose port differs from the domain', function () {
    $this->travelTo('2026-07-30T14:20:00Z');
    config()->set('siwx.domains', ['localhost:3000', 'localhost']);
    Cache::put('siwx:nonce:LZAsP6lEAHmRQuQ4', true, 600);
    $raw = str_replace('URI: http://localhost:3000', 'URI: http://localhost:3001', PHANTOM_MESSAGE);

    $code = null;

    try {
        app(SiwxVerifier::class)->verify($raw, PHANTOM_SIG);
    } catch (SiwxException $exception) {
        $code = $exception->code();
    }

    expect($code)->toBe('siwx_invalid_domain');
});

it('rejects a disabled namespace', function () {
    config()->set('siwx.namespaces', ['eip155']);

    expect(fn () => app(SiwxVerifier::class)->verify(SOLANA_MESSAGE, SOLANA_SIG_BASE64))
        ->toThrow(SiwxException::class);
});
