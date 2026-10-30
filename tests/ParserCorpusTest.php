<?php

use LexWebDev\Siwx\Exceptions\SiwxException;
use LexWebDev\Siwx\SiwxParser;

it('agrees with the go module on the shared corpus', function () {
    $cases = json_decode(file_get_contents(__DIR__ . '/fixtures/parser-corpus.json'), true);

    expect($cases)->not->toBeEmpty();

    foreach ($cases as $index => $case) {
        try {
            $message = (new SiwxParser)->parse($case['raw']);
        } catch (SiwxException $exception) {
            expect($case['expected'])->toBe(['ok' => false, 'code' => $exception->code()], "case {$index}");

            continue;
        }

        $format = 'Y-m-d\\TH:i:s';

        expect([
            'ok' => true,
            'raw' => $message->raw,
            'scheme' => $message->scheme ?? '',
            'domain' => $message->domain,
            'network' => $message->network,
            'address' => $message->address,
            'statement' => $message->statement ?? '',
            'signedStatement' => $message->signedStatement ?? '',
            'uri' => $message->uri,
            'version' => $message->version,
            'namespace' => $message->namespace,
            'chainId' => $message->chainId,
            'nonce' => $message->nonce,
            'issuedAt' => $message->issuedAt->utc()->format($format),
            'expiration' => $message->expirationTime?->utc()->format($format) ?? '',
            'notBefore' => $message->notBefore?->utc()->format($format) ?? '',
            'requestId' => $message->requestId ?? '',
            'resources' => $message->resources,
        ])->toBe($case['expected'], "case {$index}");
    }
});
