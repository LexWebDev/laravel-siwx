<?php

namespace LexWebDev\Siwx\Verifiers;

use LexWebDev\Siwx\Contracts\SignatureVerifier;
use LexWebDev\Siwx\SiwxMessage;
use StephenHill\Base58;
use Throwable;

final class SolanaVerifier implements SignatureVerifier
{
    private const KEY_BYTES = 32;
    private const SIGNATURE_BYTES = 64;

    public function __construct(private readonly ?Base58 $base58 = null) {}

    public function namespace(): string
    {
        return 'solana';
    }

    public function verify(SiwxMessage $message, string $signature): bool
    {
        $publicKey = $this->decodeBase58($message->address);
        $rawSignature = $this->decodeSignature($signature);

        if ($publicKey === null || strlen($publicKey) !== self::KEY_BYTES) {
            return false;
        }

        if ($rawSignature === null || strlen($rawSignature) !== self::SIGNATURE_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($rawSignature, $message->raw, $publicKey);
        } catch (Throwable) {
            return false;
        }
    }

    public function normaliseAddress(string $address): string
    {
        return $address;
    }

    private function decodeSignature(string $signature): ?string
    {
        $decoded = $this->decodeBase58($signature);

        if ($decoded !== null && strlen($decoded) === self::SIGNATURE_BYTES) {
            return $decoded;
        }

        $base64 = base64_decode($signature, true);

        return $base64 === false ? null : $base64;
    }

    private function decodeBase58(string $value): ?string
    {
        try {
            return ($this->base58 ?? new Base58)->decode($value);
        } catch (Throwable) {
            return null;
        }
    }
}
