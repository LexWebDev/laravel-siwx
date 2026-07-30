<?php

namespace LexWebDev\Siwx\Verifiers;

use Elliptic\EC;
use kornrunner\Keccak;
use LexWebDev\Siwx\Contracts\SignatureVerifier;
use LexWebDev\Siwx\SiwxMessage;
use Throwable;

final class Eip155Verifier implements SignatureVerifier
{
    public function namespace(): string
    {
        return 'eip155';
    }

    public function verify(SiwxMessage $message, string $signature): bool
    {
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $message->address)) {
            return false;
        }

        $recovered = $this->recover($message->raw, $signature);

        return $recovered !== null
            && $this->normaliseAddress($recovered) === $this->normaliseAddress($message->address);
    }

    public function normaliseAddress(string $address): string
    {
        return strtolower($address);
    }

    public function hash(string $message): string
    {
        return Keccak::hash("\x19Ethereum Signed Message:\n" . strlen($message) . $message, 256);
    }

    public function recover(string $message, string $signature): ?string
    {
        $sig = str_starts_with($signature, '0x') ? substr($signature, 2) : $signature;

        if (strlen($sig) !== 130 || ! ctype_xdigit($sig)) {
            return null;
        }

        $v = hexdec(substr($sig, 128, 2));
        $recoveryId = $v >= 27 ? $v - 27 : $v;

        if ($recoveryId !== 0 && $recoveryId !== 1) {
            return null;
        }

        try {
            $publicKey = (new EC('secp256k1'))->recoverPubKey(
                $this->hash($message),
                ['r' => substr($sig, 0, 64), 's' => substr($sig, 64, 64)],
                $recoveryId,
            )->encode('hex');
        } catch (Throwable) {
            return null;
        }

        return '0x' . substr(Keccak::hash(hex2bin(substr($publicKey, 2)), 256), -40);
    }
}
