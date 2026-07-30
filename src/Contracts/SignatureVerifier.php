<?php

namespace LexWebDev\Siwx\Contracts;

use LexWebDev\Siwx\SiwxMessage;

interface SignatureVerifier
{
    public function namespace(): string;

    public function verify(SiwxMessage $message, string $signature): bool;

    public function normaliseAddress(string $address): string;
}
