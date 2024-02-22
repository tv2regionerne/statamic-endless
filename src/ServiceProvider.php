<?php

namespace Tv2regionerne\StatamicEndless;

use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Tags\Collection\Collection;
use Statamic\View\Antlers\Language\Nodes\AntlersNode;
use Statamic\View\Antlers\Language\Parser\DocumentParser;
use Tv2regionerne\StatamicEndless\Livewire\Endless;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        $this
            ->bootLivewire()
            ->bootTags();
    }

    public function bootLivewire()
    {
        Livewire::component('statamic-endless', Endless::class);

        return $this;
    }

    public function bootTags()
    {
        Collection::macro('endless', function () {
            $hash = md5(serialize([
                $this->tag,
                $this->content,
                $this->params->toArray(),
            ]));

            $key = 'statamic-endless.'.$hash;

            Cache::rememberForever($key, function () {
                $as = $this->params->get('as', $this->defaultAsKey);

                $parser = tap(app(DocumentParser::class))->parse($this->content);
                $loop = collect($parser->getNodes())
                    ->first(function ($node) use ($as) {
                        return $node instanceof AntlersNode
                            && $node->name != null
                            && $node->name->name == $as;
                    });

                $provide = collect(explode('|', $this->params->get('provide')))
                    ->filter()
                    ->mapWithKeys(function ($key) {
                        return [$key => $this->context->get($key)];
                    });

                return [
                    'tag' => 'collection',
                    'params' => $this->params->toArray(),
                    'provide' => $provide->toArray(),
                    'main' => $this->content,
                    'loop' => $loop?->documentText(),
                ];
            });

            return Livewire::mount('statamic-endless', ['hash' => $hash]);
        });

        return $this;
    }
}
