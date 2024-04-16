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
        @php [$html, $data] = $this->outputMain(); @endphp
        <div x-data='{
            ...@json($data),
            loading: false,
            trigger() {
                this.loading = true;
                return this.$wire.trigger()
                    .then(([ html, data ]) => {
                        this.loading = false;
                        Object.assign(this, data);
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
        $antlersData = $this->antlersData();

        return [
            (string) Antlers::parse($this->config['main'], $antlersData),
            $this->alpineData($antlersData),
        ];
    }

    protected function outputLoop()
    {
        $antlersData = $this->antlersData();

        return [
            (string) Antlers::parse($this->config['loop'], $antlersData),
            $this->alpineData($antlersData),
        ];
    }

    protected function antlersData()
    {
        $tag = app(Loader::class)
            ->load($this->config['tag'], [
                'params' => $this->config['params'],
                'parser' => null,
                'content' => null,
                'context' => $this->config['context'],
            ]);

        return [
            ...$this->config['context'],
            ...$tag->index() ?? [],
        ];
    }

    protected function alpineData($antlersData)
    {
        $data = [];

        $params = $this->config['params'];

        if (isset($params['paginate']) && isset($antlersData['paginate'])) {
            $paginate = $antlersData['paginate'];
            $data['paginate'] = collect($paginate)
                ->only(['total_items', 'items_per_page', 'total_pages', 'current_page'])
                ->merge(['has_more_pages' => $paginate['total_pages'] > $paginate['current_page']])
                ->all();
        }

        return $data;
    }
}
