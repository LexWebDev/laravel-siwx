<?php

use LexWebDev\Siwx\SignerKind;
use LexWebDev\Siwx\SiwxParser;
use LexWebDev\Siwx\Verifiers\SolanaVerifier;

it('declares its namespace', function () {
    expect((new SolanaVerifier)->namespace())->toBe('solana');
});

it('verifies a base64 encoded signature', function () {
    $message = (new SiwxParser)->parse(SOLANA_MESSAGE);

    expect((new SolanaVerifier)->verify($message, SOLANA_SIG_BASE64))->toBe(SignerKind::Account);
});

it('verifies the same signature encoded as base58', function () {
    $message = (new SiwxParser)->parse(SOLANA_MESSAGE);

    expect((new SolanaVerifier)->verify($message, SOLANA_SIG_BASE58))->toBe(SignerKind::Account);
});

it('rejects a tampered message', function () {
    $message = (new SiwxParser)->parse(str_replace('Nonce: 8Kc2fQ1pXm9Zr4Lt', 'Nonce: 8Kc2fQ1pXm9Zr4Lu', SOLANA_MESSAGE));

    expect((new SolanaVerifier)->verify($message, SOLANA_SIG_BASE64))->toBe(SignerKind::None);
});

it('rejects a signature that is not 64 bytes', function () {
    $message = (new SiwxParser)->parse(SOLANA_MESSAGE);

    expect((new SolanaVerifier)->verify($message, base64_encode('too short')))->toBe(SignerKind::None);
});

it('rejects an address that is not a 32 byte key', function () {
    $raw = str_replace(SOLANA_SIGNER, 'notavalidbase58address', SOLANA_MESSAGE);
    $message = (new SiwxParser)->parse($raw);

    expect((new SolanaVerifier)->verify($message, SOLANA_SIG_BASE64))->toBe(SignerKind::None);
});

it('verifies a real capture from the Phantom extension', function () {
    $message = (new SiwxParser)->parse(PHANTOM_MESSAGE);

    expect((new SolanaVerifier)->verify($message, PHANTOM_SIG))->toBe(SignerKind::Account)
        ->and($message->address)->toBe(PHANTOM_SIGNER)
        ->and($message->namespace)->toBe('solana');
});

it('keeps base58 address casing intact', function () {
    expect((new SolanaVerifier)->normaliseAddress(SOLANA_SIGNER))->toBe(SOLANA_SIGNER);
});
