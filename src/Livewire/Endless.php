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

        return [
            'html' => $this->renderLoop(),
        ];
    }

    public function render()
    {
        return <<<'HTML'
        <div x-data="{
            loading: false,
            trigger() {
                this.loading = true;
                this.$wire.trigger()
                    .then(({ html }) => {
                        this.loading = false;
                        this.$refs.append?.insertAdjacentHTML('beforeend', html);
                        this.$refs.prepend?.insertAdjacentHTML('afterbegin', html);
                    });
            },
        }">{!! $this->renderMain() !!}</div>
        HTML;
    }

    protected function renderMain()
    {
        return (string) Antlers::parse($this->config['main'], $this->viewParams());
    }

    protected function renderLoop()
    {
        return (string) Antlers::parse($this->config['loop'], $this->viewParams());
    }

    protected function viewParams()
    {
        $tag = app(Loader::class)
            ->load($this->config['tag'], [
                'params' => $this->config['params'],
                'parser' => null,
                'content' => null,
                'context' => null,
            ]);

        return [
            ...$this->config['params'],
            ...$this->config['provide'],
            ...$tag->index(),
        ];
    }
}
