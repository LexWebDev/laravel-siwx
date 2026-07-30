<?php

use LexWebDev\Siwx\Exceptions\SiwxException;
use LexWebDev\Siwx\SiwxVerifier;

it('returns a nonce', function () {
    $this->getJson('/siwx/nonce')
        ->assertOk()
        ->assertJsonStructure(['nonce']);
});

it('returns a different nonce each call', function () {
    $first = $this->getJson('/siwx/nonce')->json('nonce');
    $second = $this->getJson('/siwx/nonce')->json('nonce');

    expect($first)->not->toBe($second);
});

it('rejects a message whose nonce was swapped after signing', function () {
    $this->travelTo('2026-07-30T12:40:00Z');

    config()->set('siwx.domains', ['dapp.expert']);

    $nonce = $this->getJson('/siwx/nonce')->json('nonce');
    $message = str_replace('8Kc2fQ1pXm9Zr4Lt', $nonce, EVM_MESSAGE);

    // Подмена nonce ломает подпись. Код обязан быть именно siwx_invalid_signature:
    // если проверка падает на чём-то другом, подпись где-то не проверяется всерьёз.
    expect(fn () => app(SiwxVerifier::class)->verify($message, EVM_SIG))
        ->toThrow(SiwxException::class);

    try {
        app(SiwxVerifier::class)->verify($message, EVM_SIG);
    } catch (SiwxException $exception) {
        expect($exception->code())->toBe('siwx_invalid_signature');
    }
});
