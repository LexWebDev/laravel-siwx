<?php

use LexWebDev\Siwx\Contracts\NonceRepository;
use LexWebDev\Siwx\Exceptions\SiwxException;

it('issues a nonce that satisfies EIP-4361', function () {
    expect(app(NonceRepository::class)->issue())->toMatch('/^[a-zA-Z0-9]{8,}$/');
});

it('consumes a nonce exactly once', function () {
    $repository = app(NonceRepository::class);
    $nonce = $repository->issue();

    $repository->consume($nonce);

    expect(fn () => $repository->consume($nonce))->toThrow(SiwxException::class);
});

it('rejects a nonce it never issued', function () {
    app(NonceRepository::class)->consume('neverIssued123');
})->throws(SiwxException::class);

it('rejects an expired nonce', function () {
    config()->set('siwx.nonce_ttl', 60);

    $repository = app(NonceRepository::class);
    $nonce = $repository->issue();

    $this->travel(61)->seconds();

    expect(fn () => $repository->consume($nonce))->toThrow(SiwxException::class);
});

it('issues unique nonces', function () {
    $repository = app(NonceRepository::class);

    expect($repository->issue())->not->toBe($repository->issue());
});
