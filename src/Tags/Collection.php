<?php

namespace Tv2regionerne\StatamicEndless\Tags;

use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Statamic\Tags\Collection\Collection as StatamicCollection;
use Statamic\View\Antlers\Language\Nodes\AntlersNode;
use Statamic\View\Antlers\Language\Parser\DocumentParser;

class Collection extends StatamicCollection
{
    public function endless()
    {
        $hash = md5(serialize([
            $this->tag,
            $this->content,
            $this->params->toArray(),
        ]));

        $key = 'statamic-endless-'.$hash;

        Cache::rememberForever($key, function () {
            $as = $this->params->get('as', $this->defaultAsKey);

            $parser = app(DocumentParser::class);
            $nodes = $parser->parse($this->content);

            $loop = collect($nodes)
                ->first(function ($node) use ($as) {
                    return $node instanceof AntlersNode
                        && $node->name != null
                        && $node->name->name == $as;
                });

            return [
                'tag' => 'collection',
                'params' => $this->params->toArray(),
                'main' => $this->content,
                'loop' => $loop?->documentText(),
            ];
        });

        return Livewire::mount('statamic-endless', ['hash' => $hash]);
    }
}
