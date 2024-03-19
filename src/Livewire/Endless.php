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
        
        [$html, $params] = $this->renderLoop();

        return [
            'html' => $html,
            'paginate' => $this->getPaginateFromParams($params),
        ];
    }

    public function render()
    {
        [$html, $params] = $this->renderMain();
        
        $paginate = $this->getPaginateFromParams($params);
        
        return <<<'HTML'
        @php [$html, $params] = $this->renderMain(); @endphp
        <div x-data='{
            loading: false,
            paginate: @json($this->getPaginateFromParams($params)),
            trigger() {
                this.loading = true;
                this.$wire.trigger()
                    .then(({ html, paginate }) => {
                        this.loading = false;
                        this.paginate = paginate;
                        const divFragment = document.createRange().createContextualFragment(html);
                        this.$refs.append?.appendChild(divFragment.cloneNode(true));
                        if (this.$refs.prepend) {
                            this.$refs.prepend.insertBefore(divFragment, this.$refs.prepend.firstChild);
                        }
                    });
            },
        }'>{!! $html !!}</div>
        HTML;
    }

    protected function renderMain()
    {
        $params = $this->viewParams();
        
        return [
            (string) Antlers::parse($this->config['main'], $params),
            $params,
        ];
    }

    protected function renderLoop()
    {
        $params = $this->viewParams();

        return [
            (string) Antlers::parse($this->config['loop'], $params),
            $params,
        ];
    }

    protected function viewParams()
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
            ...$tag->index(),
        ];
    }
    
    protected function getPaginateFromParams($params)
    {
        if (! isset($params['paginate'])) {
            return false;
        }
        
        $paginate = $params['paginate'];
        
        $paginate['has_more_pages'] = $paginate['total_pages'] > $paginate['current_page'];

        return collect($paginate)
            ->except(['auto_links'])
            ->all();        
    }
}
