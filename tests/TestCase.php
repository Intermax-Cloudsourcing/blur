<?php

namespace Intermax\Blur\Tests;

use Intermax\Blur\BlurServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            BlurServiceProvider::class,
        ];
    }
}
