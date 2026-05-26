<?php

namespace Tv2regionerne\StatamicEndless\Tests;

use Illuminate\Encryption\Encrypter;
use Livewire\LivewireServiceProvider;
use Statamic\Testing\AddonTestCase;
use Tv2regionerne\StatamicEndless\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            LivewireServiceProvider::class,
        ]);
    }

    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(
            Encrypter::generateKey($app['config']['app.cipher'])
        ));
    }
}
