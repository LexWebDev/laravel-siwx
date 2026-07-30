<?php

namespace LexWebDev\Siwx;

use Illuminate\Support\Facades\Http;
use LexWebDev\Siwx\Contracts\ContractSignatureChecker;
use Throwable;

final class RpcContractSignatureChecker implements ContractSignatureChecker
{
    private const MAGIC_VALUE = '0x1626ba7e';

    public function __construct(private readonly ?string $rpcUrl) {}

    public function isValid(string $address, string $hash, string $signature): bool
    {
        if (! $this->rpcUrl) {
            return false;
        }

        try {
            $result = Http::timeout(5)
                ->post($this->rpcUrl, [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'eth_call',
                    'params' => [
                        ['to' => $address, 'data' => $this->calldata($hash, $signature)],
                        'latest',
                    ],
                ])
                ->throw()
                ->json('result');
        } catch (Throwable) {
            return false;
        }

        return is_string($result) && str_starts_with($result, self::MAGIC_VALUE);
    }

    private function calldata(string $hash, string $signature): string
    {
        $sig = str_starts_with($signature, '0x') ? substr($signature, 2) : $signature;
        $bytes = intdiv(strlen($sig), 2);
        $padded = str_pad($sig, (int) ceil($bytes / 32) * 64, '0', STR_PAD_RIGHT);
        $cleanHash = str_starts_with($hash, '0x') ? substr($hash, 2) : $hash;

        return self::MAGIC_VALUE
            . str_pad($cleanHash, 64, '0', STR_PAD_LEFT)
            . str_pad(dechex(64), 64, '0', STR_PAD_LEFT)
            . str_pad(dechex($bytes), 64, '0', STR_PAD_LEFT)
            . $padded;
    }
}
