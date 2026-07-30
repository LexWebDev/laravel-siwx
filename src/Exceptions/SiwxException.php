<?php

namespace LexWebDev\Siwx\Exceptions;

use RuntimeException;

class SiwxException extends RuntimeException
{
    public function __construct(private readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }

    public function code(): string
    {
        return $this->errorCode;
    }
}
