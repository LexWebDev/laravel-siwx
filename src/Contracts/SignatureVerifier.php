<?php

namespace LexWebDev\Siwx\Contracts;

use LexWebDev\Siwx\SignerKind;
use LexWebDev\Siwx\SiwxMessage;

interface SignatureVerifier
{
    public function namespace(): string;

    public function verify(SiwxMessage $message, string $signature): SignerKind;

    public function normaliseAddress(string $address): string;
}
