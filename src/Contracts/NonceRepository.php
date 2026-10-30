<?php

namespace LexWebDev\Siwx\Contracts;

interface NonceRepository
{
    public function issue(): string;

    public function exists(string $nonce): bool;

    public function consume(string $nonce): void;
}
