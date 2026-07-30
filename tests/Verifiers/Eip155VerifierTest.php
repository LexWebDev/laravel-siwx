<?php

use LexWebDev\Siwx\SiwxParser;
use LexWebDev\Siwx\Verifiers\Eip155Verifier;

it('declares its namespace', function () {
    expect((new Eip155Verifier)->namespace())->toBe('eip155');
});

it('verifies a plain message', function () {
    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect((new Eip155Verifier)->verify($message, EVM_SIG))->toBeTrue();
});

it('verifies a one-click auth message with resources', function () {
    $message = (new SiwxParser)->parse(EVM_RESOURCES_MESSAGE);

    expect((new Eip155Verifier)->verify($message, EVM_RESOURCES_SIG))->toBeTrue();
});

it('accepts a legacy v of 0 or 1', function () {
    $message = (new SiwxParser)->parse(EVM_MESSAGE);
    $legacy = substr(EVM_SIG, 0, -2) . '01';

    expect((new Eip155Verifier)->verify($message, $legacy))->toBeTrue();
});

it('rejects a signature from another wallet', function () {
    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect((new Eip155Verifier)->verify($message, EVM_FOREIGN_SIG))->toBeFalse();
});

it('rejects a tampered message', function () {
    $message = (new SiwxParser)->parse(str_replace('Chain ID: 1', 'Chain ID: 137', EVM_MESSAGE));

    expect((new Eip155Verifier)->verify($message, EVM_SIG))->toBeFalse();
});

it('rejects a malformed signature', function () {
    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect((new Eip155Verifier)->verify($message, '0xdead'))->toBeFalse()
        ->and((new Eip155Verifier)->verify($message, substr(EVM_SIG, 0, -2) . '09'))->toBeFalse();
});

it('lowercases addresses', function () {
    expect((new Eip155Verifier)->normaliseAddress(EVM_SIGNER))->toBe(strtolower(EVM_SIGNER));
});
