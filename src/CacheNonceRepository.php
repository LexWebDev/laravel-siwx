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
        $store = $this->cache->store($this->store);
        $key = $this->key($nonce);

        if (! $store->has($key) || ! $store->add($key . ':consumed', true, $this->ttl)) {
            throw new SiwxException('siwx_invalid_nonce');
        }

        $store->forget($key);
    }

    private function key(string $nonce): string
    {
        return 'siwx:nonce:' . $nonce;
    }
}
