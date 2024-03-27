<?php

namespace Tv2regionerne\StatamicEndless\Livewire;

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Statamic\Facades\Antlers;
use Statamic\Tags\Loader;

class Endless extends Component
{
    use WithPagination, WithoutUrlPagination;

    #[Locked]
    public $hash;

    protected $config;

    public function mount()
    {
        $this->config = Cache::get('statamic-endless.'.$this->hash);
    }

    public function hydrate()
    {
        $this->config = Cache::get('statamic-endless.'.$this->hash);
    }

    #[Renderless]
    public function trigger()
    {
        $this->nextPage();

        return $this->outputLoop();
    }

    public function render()
    {
        return <<<'HTML'
        @php [$html, $params] = $this->outputMain(); @endphp
        <div x-data='{
            ...@json($params),
            loading: false,
            trigger() {
                this.loading = true;
                this.$wire.trigger()
                    .then(([ html, params ]) => {
                        this.loading = false;
                        Object.assign(this, params);
                        const fragment = document.createRange().createContextualFragment(html);
                        this.$refs.append?.appendChild(fragment.cloneNode(true));
                        this.$refs.prepend?.insertBefore(fragment.cloneNode(true), this.$refs.prepend.firstChild);
                    });
            },
        }'>{!! $html !!}</div>
        HTML;
    }

    protected function outputMain()
    {
        $params = $this->antlersParams();

        return [
            (string) Antlers::parse($this->config['main'], $params),
            $this->alpineParams($params),
        ];
    }

    protected function outputLoop()
    {
        $params = $this->antlersParams();

        return [
            (string) Antlers::parse($this->config['loop'], $params),
            $this->alpineParams($params),
        ];
    }

    protected function antlersParams()
    {
        $params = $this->config['params'];

        if ($limit = $params['limit'] ?? null) {
            $params['paginate'] = $limit;
            unset($params['limit']);
        }

        $tag = app(Loader::class)
            ->load($this->config['tag'], [
                'params' => $params,
                'parser' => null,
                'content' => null,
                'context' => $this->config['context'],
            ]);

        return [
            ...$this->config['context'],
            ...$tag->index(),
        ];
    }

    protected function alpineParams($params)
    {
        $paginate = $params['paginate'] ?? null;

        if ($paginate) {
            $paginate = collect($paginate)
                ->only(['total_items', 'items_per_page', 'total_pages', 'current_page'])
                ->merge(['has_more_pages' => $paginate['total_pages'] > $paginate['current_page']])
                ->all();
        }

        return [
            'paginate' => $paginate,
        ];
    }
}
