<?php

namespace LexWebDev\Siwx;

use Carbon\CarbonImmutable;

final readonly class VerifiedSiwxSession
{
    public function __construct(
        public string $address,
        public string $namespace,
        public string $chainId,
        public string $domain,
        public CarbonImmutable $issuedAt,
        public SiwxMessage $message,
    ) {}
}
