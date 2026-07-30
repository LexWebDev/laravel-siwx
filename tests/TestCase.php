<?php

namespace LexWebDev\Siwx\Tests;

use LexWebDev\Siwx\SiwxServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SiwxServiceProvider::class];
    }
}
