<?php

namespace LexWebDev\Siwx;

use Carbon\CarbonImmutable;
use LexWebDev\Siwx\Exceptions\SiwxException;
use Throwable;

final class SiwxParser
{
    private const HEADER = '/^(?P<domain>[^\s]+) wants you to sign in with your (?P<network>.+) account:$/u';

    private const FIELD_KEYS = [
        'URI', 'Version', 'Chain ID', 'Nonce',
        'Issued At', 'Expiration Time', 'Not Before', 'Request ID',
    ];

    public function parse(string $raw): SiwxMessage
    {
        $normalised = trim(str_replace("\r\n", "\n", $raw));
        $lines = explode("\n", $normalised);

        if (! preg_match(self::HEADER, array_shift($lines) ?? '', $header)) {
            throw new SiwxException('siwx_invalid_message');
        }

        $address = trim(array_shift($lines) ?? '');

        if ($address === '') {
            throw new SiwxException('siwx_invalid_message');
        }

        $statement = $this->extractStatement($lines);
        $fields = $this->extractFields($lines);

        [$namespace, $chainId] = $this->resolveChain($this->required($fields, 'Chain ID'));

        return new SiwxMessage(
            raw: $normalised,
            domain: $header['domain'],
            network: $header['network'],
            address: $address,
            statement: $statement,
            uri: $this->required($fields, 'URI'),
            version: $this->required($fields, 'Version'),
            namespace: $namespace,
            chainId: $chainId,
            nonce: $this->nonce($fields),
            issuedAt: $this->time($this->required($fields, 'Issued At')),
            expirationTime: isset($fields['Expiration Time']) ? $this->time($fields['Expiration Time']) : null,
            notBefore: isset($fields['Not Before']) ? $this->time($fields['Not Before']) : null,
        );
    }

    private function extractStatement(array &$lines): ?string
    {
        while (($lines[0] ?? null) === '') {
            array_shift($lines);
        }

        $candidate = $lines[0] ?? '';

        foreach (self::FIELD_KEYS as $key) {
            if (str_starts_with($candidate, $key . ': ')) {
                return null;
            }
        }

        array_shift($lines);

        return $candidate === '' ? null : $candidate;
    }

    private function extractFields(array $lines): array
    {
        $fields = [];

        foreach ($lines as $line) {
            if ($line === '' || $line === 'Resources:' || str_starts_with($line, '- ')) {
                continue;
            }

            $parts = explode(': ', $line, 2);

            if (count($parts) === 2) {
                $fields[$parts[0]] = $parts[1];
            }
        }

        return $fields;
    }

    private function resolveChain(string $value): array
    {
        if (str_contains($value, ':')) {
            [$namespace, $reference] = explode(':', $value, 2);

            if ($namespace === '' || $reference === '') {
                throw new SiwxException('siwx_invalid_message');
            }

            return [$namespace, $namespace . ':' . $reference];
        }

        if (! ctype_digit($value)) {
            throw new SiwxException('siwx_invalid_message');
        }

        return ['eip155', 'eip155:' . $value];
    }

    private function required(array $fields, string $key): string
    {
        return $fields[$key] ?? throw new SiwxException('siwx_invalid_message');
    }

    private function nonce(array $fields): string
    {
        $nonce = $this->required($fields, 'Nonce');

        if (! preg_match('/^[a-zA-Z0-9]{8,}$/', $nonce)) {
            throw new SiwxException('siwx_invalid_message');
        }

        return $nonce;
    }

    private function time(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            throw new SiwxException('siwx_invalid_message');
        }
    }
}
