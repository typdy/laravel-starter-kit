<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests;

use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Typdy\StarterKit\Laravel\ServiceProvider as TypdyServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @return array<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [TypdyServiceProvider::class];
    }
}
