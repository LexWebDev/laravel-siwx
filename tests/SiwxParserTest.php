<?php

use LexWebDev\Siwx\Exceptions\SiwxException;
use LexWebDev\Siwx\SiwxParser;

it('parses an evm message with a numeric chain id', function () {
    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect($message->domain)->toBe('dapp.expert')
        ->and($message->network)->toBe('Ethereum')
        ->and($message->address)->toBe(EVM_SIGNER)
        ->and($message->statement)->toBe('Sign in with Ethereum to the application.')
        ->and($message->uri)->toBe('https://dapp.expert')
        ->and($message->version)->toBe('1')
        ->and($message->namespace)->toBe('eip155')
        ->and($message->chainId)->toBe('eip155:1')
        ->and($message->nonce)->toBe('8Kc2fQ1pXm9Zr4Lt')
        ->and($message->issuedAt->toIso8601ZuluString())->toBe('2026-07-30T12:34:56Z');
});

it('parses a solana message with a caip-2 chain id', function () {
    $message = (new SiwxParser)->parse(SOLANA_MESSAGE);

    expect($message->network)->toBe('Solana')
        ->and($message->address)->toBe(SOLANA_SIGNER)
        ->and($message->namespace)->toBe('solana')
        ->and($message->chainId)->toBe('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp');
});

it('accepts an eip155 chain id already in caip-2 form', function () {
    $raw = str_replace('Chain ID: 1', 'Chain ID: eip155:137', EVM_MESSAGE);

    $message = (new SiwxParser)->parse($raw);

    expect($message->namespace)->toBe('eip155')
        ->and($message->chainId)->toBe('eip155:137');
});

it('ignores the resources section from one-click auth', function () {
    $message = (new SiwxParser)->parse(EVM_RESOURCES_MESSAGE);

    expect($message->nonce)->toBe('8Kc2fQ1pXm9Zr4Lt')
        ->and($message->address)->toBe(EVM_SIGNER);
});

it('parses optional expiration and not-before', function () {
    $raw = EVM_MESSAGE
        . "\nExpiration Time: 2026-07-30T13:34:56.000Z"
        . "\nNot Before: 2026-07-30T12:00:00.000Z";

    $message = (new SiwxParser)->parse($raw);

    expect($message->expirationTime->toIso8601ZuluString())->toBe('2026-07-30T13:34:56Z')
        ->and($message->notBefore->toIso8601ZuluString())->toBe('2026-07-30T12:00:00Z');
});

it('parses a message with a single blank line and no statement', function () {
    $raw = "dapp.expert wants you to sign in with your Ethereum account:\n"
        . EVM_SIGNER . "\n\n"
        . "URI: https://dapp.expert\nVersion: 1\nChain ID: 1\n"
        . "Nonce: 8Kc2fQ1pXm9Zr4Lt\nIssued At: 2026-07-30T12:34:56.000Z";

    $message = (new SiwxParser)->parse($raw);

    expect($message->statement)->toBeNull()
        ->and($message->uri)->toBe('https://dapp.expert');
});

it('rejects a malformed header', function () {
    (new SiwxParser)->parse("garbage\n" . EVM_SIGNER);
})->throws(SiwxException::class);

it('rejects an empty address line', function () {
    (new SiwxParser)->parse(str_replace(EVM_SIGNER, '', EVM_MESSAGE));
})->throws(SiwxException::class);

it('rejects a message without a nonce', function () {
    (new SiwxParser)->parse(str_replace("Nonce: 8Kc2fQ1pXm9Zr4Lt\n", '', EVM_MESSAGE));
})->throws(SiwxException::class);

it('rejects a short nonce', function () {
    (new SiwxParser)->parse(str_replace('8Kc2fQ1pXm9Zr4Lt', 'abc', EVM_MESSAGE));
})->throws(SiwxException::class);
