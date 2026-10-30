<?php

namespace LexWebDev\Siwx\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class SiwxException extends HttpException
{
    public function __construct(private readonly string $errorCode)
    {
        parent::__construct(401, $errorCode);
    }

    public function code(): string
    {
        return $this->errorCode;
    }
}
