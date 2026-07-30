<?php

namespace LexWebDev\Siwx;

use Carbon\CarbonImmutable;

final readonly class SiwxMessage
{
    public function __construct(
        public string $raw,
        public string $domain,
        public string $network,
        public string $address,
        public ?string $statement,
        public string $uri,
        public string $version,
        public string $namespace,
        public string $chainId,
        public string $nonce,
        public CarbonImmutable $issuedAt,
        public ?CarbonImmutable $expirationTime,
        public ?CarbonImmutable $notBefore,
    ) {}
}
