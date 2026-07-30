<?php

namespace LexWebDev\Siwx\Contracts;

interface ContractSignatureChecker
{
    public function isValid(string $address, string $hash, string $signature): bool;
}
