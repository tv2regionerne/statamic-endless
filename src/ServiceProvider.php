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
            $params = $this->params;
            $context = $this->context
                ->only(explode('|', $params->get('context')));

            $hash = md5(serialize([
                $this->tag,
                $this->content,
                $params->toArray(),
                $context->toArray(),
            ]));

            $key = 'statamic-endless.'.$hash;

            Cache::rememberForever($key, function () use ($params, $context) {
                $as = $params->get('as', $this->defaultAsKey);

                $parser = tap(app(DocumentParser::class))->parse($this->content);
                $loop = collect($parser->getNodes())
                    ->first(function ($node) use ($as) {
                        return $node instanceof AntlersNode
                            && $node->name != null
                            && $node->name->name == $as;
                    });

                if (! $loop) {
                    throw new \Exception('No loop template node found');
                }

                return [
                    'tag' => 'collection',
                    'main' => $this->content,
                    'loop' => $loop->documentText(),
                    'params' => $params->toArray(),
                    'context' => $context->toArray(),
                ];
            });

            return Livewire::mount('statamic-endless', ['hash' => $hash]);
        });

        return $this;
    }
}
