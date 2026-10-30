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

const RECAP_SENTENCE = 'I further authorize the stated URI to perform the following actions on my behalf: '
    . "(1) 'request': 'personal_sign' for 'eip155'.";

const RECAP_RESOURCE = 'urn:recap:eyJhdHQiOnsiZWlwMTU1Ijp7InJlcXVlc3QvcGVyc29uYWxfc2lnbiI6W3t9XX19fQ';

it('separates the erc-5573 recap sentence from the statement on one-click auth', function () {
    $raw = str_replace(
        'Sign in with Ethereum to the application.',
        'Sign in with Ethereum to the application. ' . RECAP_SENTENCE,
        EVM_RESOURCES_MESSAGE,
    );

    $message = (new SiwxParser)->parse($raw);

    expect($message->statement)->toBe('Sign in with Ethereum to the application.')
        ->and($message->signedStatement)->toBe('Sign in with Ethereum to the application. ' . RECAP_SENTENCE)
        ->and($message->resources)->toBe([RECAP_RESOURCE]);
});

it('yields a null statement when one-click auth only carried the recap sentence', function () {
    $raw = str_replace('Sign in with Ethereum to the application.', RECAP_SENTENCE, EVM_RESOURCES_MESSAGE);

    $message = (new SiwxParser)->parse($raw);

    expect($message->statement)->toBeNull()
        ->and($message->signedStatement)->toBe(RECAP_SENTENCE);
});

it('leaves the statement untouched when no recap resource backs the sentence', function () {
    $raw = str_replace(
        'Sign in with Ethereum to the application.',
        'Sign in with Ethereum to the application. ' . RECAP_SENTENCE,
        EVM_MESSAGE,
    );

    $message = (new SiwxParser)->parse($raw);

    expect($message->statement)->toBe('Sign in with Ethereum to the application. ' . RECAP_SENTENCE)
        ->and($message->resources)->toBe([]);
});

it('exposes the signed statement and resources on a plain message', function () {
    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect($message->signedStatement)->toBe('Sign in with Ethereum to the application.')
        ->and($message->resources)->toBe([]);
});

it('rejects a message that breaks the eip-4361 grammar', function (string $raw) {
    (new SiwxParser)->parse($raw);
})->with([
    'duplicate uri' => [EVM_MESSAGE . "\nURI: https://dapp.expert"],
    'duplicate nonce' => [EVM_MESSAGE . "\nNonce: zzzz9999zzzz"],
    'field after resources' => [EVM_RESOURCES_MESSAGE . "\nNonce: zzzz9999zzzz"],
    'second resources section' => [EVM_RESOURCES_MESSAGE . "\nResources:\n- https://dapp.expert"],
    'unknown field' => [EVM_MESSAGE . "\nInjected: value"],
    'resource without a section' => [EVM_MESSAGE . "\n- https://dapp.expert"],
    'second statement line' => [str_replace(
        'Sign in with Ethereum to the application.',
        "Sign in with Ethereum to the application.\nSecond line.",
        EVM_MESSAGE,
    )],
    'relative issued at' => [str_replace('2026-07-30T12:34:56.000Z', 'now', EVM_MESSAGE)],
    'relative expiration' => [EVM_MESSAGE . "\nExpiration Time: +100 years"],
    'date without time' => [EVM_MESSAGE . "\nNot Before: 2026-07-30"],
    'date without offset' => [EVM_MESSAGE . "\nNot Before: 2026-07-30T12:34:56"],
    'impossible date' => [EVM_MESSAGE . "\nNot Before: 2026-02-31T12:34:56Z"],
    'sixtieth second' => [EVM_MESSAGE . "\nNot Before: 2026-07-30T12:34:60Z"],
    'empty uri' => [str_replace('URI: https://dapp.expert', 'URI: ', EVM_MESSAGE)],
    'empty version' => [str_replace('Version: 1', 'Version: ', EVM_MESSAGE)],
    'stray carriage return' => [str_replace('URI: https://dapp.expert', "URI: https://dapp.expert\r\r", EVM_MESSAGE)],
    'chain outside caip-2' => [str_replace('Chain ID: 1', 'Chain ID: eip155:!!!', EVM_MESSAGE)],
])->throws(SiwxException::class);

it('accepts every message shape real builders produce', function (string $raw) {
    expect((new SiwxParser)->parse($raw)->nonce)->toBe('8Kc2fQ1pXm9Zr4Lt');
})->with([
    'two blank lines without a statement' => ["dapp.expert wants you to sign in with your Ethereum account:\n"
        . EVM_SIGNER . "\n\n\n"
        . "URI: https://dapp.expert\nVersion: 1\nChain ID: 1\n"
        . "Nonce: 8Kc2fQ1pXm9Zr4Lt\nIssued At: 2026-07-30T12:34:56.000Z"],
    'empty resources section' => [EVM_MESSAGE . "\nResources:"],
    'all optional fields in order' => [EVM_MESSAGE
        . "\nExpiration Time: 2026-07-30T13:34:56.000Z"
        . "\nNot Before: 2026-07-30T12:00:00.000Z"
        . "\nRequest ID: req-42"
        . "\nResources:\n- https://dapp.expert/a\n- urn:recap:eyJhdHQiOnt9fQ"],
    'statement containing a colon' => [str_replace(
        'Sign in with Ethereum to the application.',
        'Note: sign in to the application.',
        EVM_MESSAGE,
    )],
    'timestamp without fraction' => [str_replace('12:34:56.000Z', '12:34:56Z', EVM_MESSAGE)],
    'timestamp with offset' => [str_replace('12:34:56.000Z', '15:34:56+03:00', EVM_MESSAGE)],
    'uri with a port and a query' => [str_replace(
        'URI: https://dapp.expert',
        'URI: https://dapp.expert:8443/login?next=/home',
        EVM_MESSAGE,
    )],
]);

it('reads the optional scheme from the header', function () {
    $message = (new SiwxParser)->parse('https://' . EVM_MESSAGE);

    expect($message->scheme)->toBe('https')
        ->and($message->domain)->toBe('dapp.expert');
});

it('has no scheme when the header carries none', function () {
    expect((new SiwxParser)->parse(EVM_MESSAGE)->scheme)->toBeNull();
});

it('keeps the port when the header carries a scheme', function () {
    $message = (new SiwxParser)->parse('http://' . PHANTOM_MESSAGE);

    expect($message->scheme)->toBe('http')
        ->and($message->domain)->toBe('localhost:3000');
});

it('exposes the request id', function () {
    $message = (new SiwxParser)->parse(EVM_MESSAGE . "\nRequest ID: req-42");

    expect($message->requestId)->toBe('req-42');
});

it('has no request id when the message carries none', function () {
    expect((new SiwxParser)->parse(EVM_MESSAGE)->requestId)->toBeNull();
});

it('reports the solana chain as it was signed', function (string $signed) {
    $raw = str_replace('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', $signed, SOLANA_MESSAGE);

    $message = (new SiwxParser)->parse($raw);

    expect($message->namespace)->toBe('solana')
        ->and($message->chainId)->toBe($signed);
})->with([
    'genesis hash from appkit' => ['solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp'],
    'alias phantom signs' => ['solana:mainnet'],
]);
