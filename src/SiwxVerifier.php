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
        $uriHost = parse_url($message->uri, PHP_URL_HOST);

        if ($this->allowedDomains === []
            || ! in_array($message->domain, $this->allowedDomains, true)
            || ! in_array($uriHost, $this->allowedDomains, true)) {
            throw new SiwxException('siwx_invalid_domain');
        }
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
