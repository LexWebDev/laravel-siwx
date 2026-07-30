<?php

namespace LexWebDev\Siwx;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Str;
use LexWebDev\Siwx\Contracts\NonceRepository;
use LexWebDev\Siwx\Exceptions\SiwxException;

final class CacheNonceRepository implements NonceRepository
{
    public function __construct(
        private readonly CacheFactory $cache,
        private readonly ?string $store,
        private readonly int $ttl,
    ) {}

    public function issue(): string
    {
        $nonce = Str::random(16);

        $this->cache->store($this->store)->put($this->key($nonce), true, $this->ttl);

        return $nonce;
    }

    public function consume(string $nonce): void
    {
        if (! $this->cache->store($this->store)->pull($this->key($nonce))) {
            throw new SiwxException('siwx_invalid_nonce');
        }
    }

    private function key(string $nonce): string
    {
        return 'siwx:nonce:' . $nonce;
    }
}
