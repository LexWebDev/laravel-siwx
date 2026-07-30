<?php

namespace LexWebDev\Siwx;

use LexWebDev\Siwx\Contracts\SignatureVerifier;
use LexWebDev\Siwx\Exceptions\SiwxException;

final class VerifierRegistry
{
    /** @var array<string, SignatureVerifier> */
    private array $verifiers = [];

    /**
     * @param  array<int, SignatureVerifier>  $verifiers
     * @param  array<int, string>  $enabled
     */
    public function __construct(array $verifiers, private readonly array $enabled)
    {
        foreach ($verifiers as $verifier) {
            $this->register($verifier);
        }
    }

    public function register(SignatureVerifier $verifier): void
    {
        $this->verifiers[$verifier->namespace()] = $verifier;
    }

    public function for(string $namespace): SignatureVerifier
    {
        if (! in_array($namespace, $this->enabled, true)) {
            throw new SiwxException('siwx_unsupported_namespace');
        }

        return $this->verifiers[$namespace] ?? throw new SiwxException('siwx_unsupported_namespace');
    }
}
