<?php

namespace LexWebDev\Siwx\Contracts;

interface NonceRepository
{
    public function issue(): string;

    public function consume(string $nonce): void;
}
