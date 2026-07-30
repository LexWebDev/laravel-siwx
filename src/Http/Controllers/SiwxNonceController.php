<?php

namespace LexWebDev\Siwx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use LexWebDev\Siwx\Contracts\NonceRepository;

final class SiwxNonceController
{
    public function __invoke(NonceRepository $nonces): JsonResponse
    {
        return new JsonResponse(['nonce' => $nonces->issue()]);
    }
}
