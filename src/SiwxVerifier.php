<?php

namespace LexWebDev\Siwx;

use Carbon\CarbonImmutable;
use LexWebDev\Siwx\Contracts\NonceRepository;
use LexWebDev\Siwx\Exceptions\SiwxException;

final class SiwxVerifier
{
    public function __construct(
        private readonly SiwxParser $parser,
        private readonly VerifierRegistry $verifiers,
        private readonly NonceRepository $nonces,
        private readonly array $allowedDomains,
        private readonly int $clockSkew,
    ) {}

    public function verify(string $rawMessage, string $signature): VerifiedSiwxSession
    {
        $message = $this->parser->parse($rawMessage);
        $verifier = $this->verifiers->for($message->namespace);

        $this->assertDomain($message);
        $this->assertVersion($message);
        $this->assertTimestamps($message);

        if (! $verifier->verify($message, $signature)) {
            throw new SiwxException('siwx_invalid_signature');
        }

        $this->nonces->consume($message->nonce);

        return new VerifiedSiwxSession(
            address: $verifier->normaliseAddress($message->address),
            namespace: $message->namespace,
            chainId: $message->chainId,
            domain: $message->domain,
            issuedAt: $message->issuedAt,
            message: $message,
        );
    }

    private function assertDomain(SiwxMessage $message): void
    {
        if ($this->allowedDomains === []
            || ! in_array($message->domain, $this->allowedDomains, true)) {
            throw new SiwxException('siwx_invalid_domain');
        }

        // Per EIP-4361 the domain line is the authority of the URI, so the two must
        // agree rather than merely both appear in the allow list. Comparing them
        // directly closes a hole: with two allowed hosts, a message could name one
        // domain and point its URI at the other. It also stops forcing callers to
        // list a host twice — once with the port, once without — because the
        // authority is rebuilt with the port when the URI carries one.
        if ($this->authorityOf($message->uri) !== $message->domain) {
            throw new SiwxException('siwx_invalid_domain');
        }
    }

    private function authorityOf(string $uri): ?string
    {
        $parts = parse_url($uri);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        return isset($parts['port'])
            ? $parts['host'] . ':' . $parts['port']
            : $parts['host'];
    }

    private function assertVersion(SiwxMessage $message): void
    {
        if ($message->version !== '1') {
            throw new SiwxException('siwx_invalid_message');
        }
    }

    private function assertTimestamps(SiwxMessage $message): void
    {
        $now = CarbonImmutable::now();

        if ($message->issuedAt->isAfter($now->addSeconds($this->clockSkew))) {
            throw new SiwxException('siwx_invalid_message');
        }

        if ($message->expirationTime?->isBefore($now)) {
            throw new SiwxException('siwx_invalid_message');
        }

        if ($message->notBefore?->isAfter($now)) {
            throw new SiwxException('siwx_invalid_message');
        }
    }
}
