<?php

namespace Tv2regionerne\StatamicEndless;

use Livewire\Livewire;
use Statamic\Providers\AddonServiceProvider;
use Tv2regionerne\StatamicEndless\Livewire\Endless;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        Livewire::component('statamic-endless', Endless::class);
    }
}
