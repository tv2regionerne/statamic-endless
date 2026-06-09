<?php

use Statamic\Tags\Loader;
use Tv2regionerne\StatamicEndless\Livewire\Endless;

/**
 * Build a testable subclass of Endless that exposes the protected methods
 * and short-circuits the Livewire machinery (no mount/hydrate needed).
 */
function makeEndless(array $config, int $page = 1): Endless
{
    $component = new class extends Endless {
        public function exposeAntlersData(): array
        {
            return $this->antlersData();
        }

        public function exposeAlpineData(array $antlersData): array
        {
            return $this->alpineData($antlersData);
        }

        public function setConfig(array $config): void
        {
            $this->config = $config;
        }

        public function setCurrentPage(int $page): void
        {
            $this->paginators['page'] = $page;
        }
    };

    $component->setConfig($config);
    $component->setCurrentPage($page);

    return $component;
}

function makeConfig(array $params = [], array $context = [], string $tag = 'collection'): array
{
    return [
        'tag'     => $tag,
        'main'    => '',
        'loop'    => '',
        'params'  => $params,
        'context' => $context,
    ];
}

function mockLoader(array $items, ?array $capturedParams = null): void
{
    $tagMock = Mockery::mock();
    $tagMock->shouldReceive('index')->once()->andReturn($items);
    $tagMock->shouldReceive('setProperties')->andReturnSelf();

    $loaderMock = Mockery::mock(Loader::class);
    $loaderMock
        ->shouldReceive('load')
        ->once()
        ->withArgs(function (string $tagName, array $props) use (&$capturedParams) {
            if ($capturedParams !== null) {
                // Let the caller inspect what params were forwarded
                foreach ($props['params'] as $k => $v) {
                    $capturedParams[$k] = $v;
                }
            }
            return true;
        })
        ->andReturn($tagMock);

    app()->instance(Loader::class, $loaderMock);
}

function bindDeduplicate(array $ids = []): object
{
    $deduplicate = new class($ids) {
        public function __construct(public array $ids = [])
        {
        }

        public function fetch(): array
        {
            return $this->ids;
        }

        public function merge(array $ids): self
        {
            $this->ids = array_merge($this->ids, $ids);

            return $this;
        }
    };

    app()->instance('deduplicate', $deduplicate);

    return $deduplicate;
}

it('passes params through unchanged when paginate is not set', function () {
    $forwardedParams = [];

    $tagMock = Mockery::mock();
    $tagMock->shouldReceive('index')->once()->andReturn(['entries' => ['a', 'b']]);

    $loaderMock = Mockery::mock(Loader::class);
    $loaderMock
        ->shouldReceive('load')
        ->once()
        ->withArgs(function (string $tagName, array $props) use (&$forwardedParams) {
            $forwardedParams = $props['params'];
            return true;
        })
        ->andReturn($tagMock);

    app()->instance(Loader::class, $loaderMock);

    $config = makeConfig(['from' => 'articles', 'limit' => '5']);
    $component = makeEndless($config);
    $component->exposeAntlersData();

    expect($forwardedParams)->toBe(['from' => 'articles', 'limit' => '5'])
        ->and($forwardedParams)->not->toHaveKey('paginate');
});

it('returns no paginate key in result when paginate param is not set', function () {
    mockLoader(['entries' => ['a', 'b']]);

    $config = makeConfig(['from' => 'articles']);
    $result = makeEndless($config)->exposeAntlersData();

    expect($result)->not->toHaveKey('paginate');
});

it('merges context into the result', function () {
    mockLoader(['entries' => []]);

    $config = makeConfig(['from' => 'articles'], ['site' => 'default']);
    $result = makeEndless($config)->exposeAntlersData();

    expect($result)->toHaveKey('site', 'default');
});

it('removes paginate param and adds limit = perPage+1 and offset=0 for page 1', function () {
    $forwardedParams = [];

    $tagMock = Mockery::mock();
    $tagMock->shouldReceive('index')->once()->andReturn(['entries' => []]);

    $loaderMock = Mockery::mock(Loader::class);
    $loaderMock
        ->shouldReceive('load')
        ->once()
        ->withArgs(function (string $tagName, array $props) use (&$forwardedParams) {
            $forwardedParams = $props['params'];
            return true;
        })
        ->andReturn($tagMock);

    app()->instance(Loader::class, $loaderMock);

    $config = makeConfig(['paginate' => '10', 'from' => 'articles']);
    makeEndless($config, page: 1)->exposeAntlersData();

    expect($forwardedParams)
        ->not->toHaveKey('paginate')
        ->toHaveKey('limit', 11)   // perPage + 1
        ->toHaveKey('offset', 0);  // (1 - 1) * 10
});

it('calculates correct offset for page 2', function () {
    $forwardedParams = [];

    $tagMock = Mockery::mock();
    $tagMock->shouldReceive('index')->once()->andReturn(['entries' => []]);

    $loaderMock = Mockery::mock(Loader::class);
    $loaderMock
        ->shouldReceive('load')
        ->once()
        ->withArgs(function (string $tagName, array $props) use (&$forwardedParams) {
            $forwardedParams = $props['params'];
            return true;
        })
        ->andReturn($tagMock);

    app()->instance(Loader::class, $loaderMock);

    $config = makeConfig(['paginate' => '10', 'from' => 'articles']);
    makeEndless($config, page: 2)->exposeAntlersData();

    expect($forwardedParams)
        ->toHaveKey('limit', 11)
        ->toHaveKey('offset', 10); // (2 - 1) * 10
});

it('calculates correct offset for page 3', function () {
    $forwardedParams = [];

    $tagMock = Mockery::mock();
    $tagMock->shouldReceive('index')->once()->andReturn(['entries' => []]);

    $loaderMock = Mockery::mock(Loader::class);
    $loaderMock
        ->shouldReceive('load')
        ->once()
        ->withArgs(function (string $tagName, array $props) use (&$forwardedParams) {
            $forwardedParams = $props['params'];
            return true;
        })
        ->andReturn($tagMock);

    app()->instance(Loader::class, $loaderMock);

    $config = makeConfig(['paginate' => '5', 'from' => 'articles']);
    makeEndless($config, page: 3)->exposeAntlersData();

    expect($forwardedParams)
        ->toHaveKey('limit', 6)
        ->toHaveKey('offset', 10); // (3 - 1) * 5
});

it('captures existing deduplicate ids before the first paginated query', function () {
    $existingIds = ['first-teaser-1', 'first-teaser-2'];
    $idsAtLoad = [];

    bindDeduplicate($existingIds);

    $tagMock = Mockery::mock();
    $tagMock->shouldReceive('index')->once()->andReturn(['entries' => []]);

    $loaderMock = Mockery::mock(Loader::class);
    $loaderMock
        ->shouldReceive('load')
        ->once()
        ->withArgs(function (string $tagName, array $props) use (&$idsAtLoad) {
            $idsAtLoad = app('deduplicate')->fetch();

            return true;
        })
        ->andReturn($tagMock);

    app()->instance(Loader::class, $loaderMock);

    $config = makeConfig(['paginate' => '2', 'deduplicate' => true, 'from' => 'articles']);
    $component = makeEndless($config, page: 1);
    $component->exposeAntlersData();

    expect($component->hasCapturedInitialDeduplicateIds)->toBeTrue()
        ->and($component->initialDeduplicateIds)->toBe($existingIds)
        ->and($idsAtLoad)->toBe($existingIds);
});

it('replays captured deduplicate ids before livewire pagination requests', function () {
    $existingIds = ['first-teaser-1', 'first-teaser-2'];
    $forwardedParams = [];
    $idsAtLoad = [];

    bindDeduplicate();

    $tagMock = Mockery::mock();
    $tagMock->shouldReceive('index')->once()->andReturn(['entries' => []]);

    $loaderMock = Mockery::mock(Loader::class);
    $loaderMock
        ->shouldReceive('load')
        ->once()
        ->withArgs(function (string $tagName, array $props) use (&$forwardedParams, &$idsAtLoad) {
            $forwardedParams = $props['params'];
            $idsAtLoad = app('deduplicate')->fetch();

            return true;
        })
        ->andReturn($tagMock);

    app()->instance(Loader::class, $loaderMock);

    $config = makeConfig(['paginate' => '2', 'deduplicate' => true, 'from' => 'articles']);
    $component = makeEndless($config, page: 2);
    $component->initialDeduplicateIds = $existingIds;
    $component->hasCapturedInitialDeduplicateIds = true;
    $component->exposeAntlersData();

    expect($idsAtLoad)->toBe($existingIds)
        ->and($forwardedParams)
        ->toHaveKey('limit', 3)
        ->toHaveKey('offset', 2);
});

it('does not replay captured deduplicate ids when deduplicate is disabled', function () {
    $idsAtLoad = [];

    bindDeduplicate();

    $tagMock = Mockery::mock();
    $tagMock->shouldReceive('index')->once()->andReturn(['entries' => []]);

    $loaderMock = Mockery::mock(Loader::class);
    $loaderMock
        ->shouldReceive('load')
        ->once()
        ->withArgs(function (string $tagName, array $props) use (&$idsAtLoad) {
            $idsAtLoad = app('deduplicate')->fetch();

            return true;
        })
        ->andReturn($tagMock);

    app()->instance(Loader::class, $loaderMock);

    $config = makeConfig(['paginate' => '2', 'deduplicate' => false, 'from' => 'articles']);
    $component = makeEndless($config, page: 2);
    $component->initialDeduplicateIds = ['first-teaser-1', 'first-teaser-2'];
    $component->hasCapturedInitialDeduplicateIds = true;
    $component->exposeAntlersData();

    expect($idsAtLoad)->toBe([]);
});

it('sets has_more_pages true and slices items when tag returns more than perPage items', function () {
    // perPage = 3, tag returns 4 items (the sentinel extra item)
    mockLoader(['entries' => ['a', 'b', 'c', 'd']]);

    $config = makeConfig(['paginate' => '3', 'from' => 'articles']);
    $result = makeEndless($config)->exposeAntlersData();

    expect($result['paginate']['has_more_pages'])->toBeTrue()
        ->and(count($result['entries']))->toBe(3)
        ->and($result['entries']->toArray())->toBe(['a', 'b', 'c']);
});

it('sets has_more_pages false and keeps all items when tag returns exactly perPage items', function () {
    mockLoader(['entries' => ['a', 'b', 'c']]);

    $config = makeConfig(['paginate' => '3', 'from' => 'articles']);
    $result = makeEndless($config)->exposeAntlersData();

    expect($result['paginate']['has_more_pages'])->toBeFalse()
        ->and(count($result['entries']))->toBe(3);
});

it('sets has_more_pages false when tag returns fewer than perPage items', function () {
    mockLoader(['entries' => ['a', 'b']]);

    $config = makeConfig(['paginate' => '3', 'from' => 'articles']);
    $result = makeEndless($config)->exposeAntlersData();

    expect($result['paginate']['has_more_pages'])->toBeFalse()
        ->and(count($result['entries']))->toBe(2);
});

it('sets has_more_pages false when tag returns empty result', function () {
    mockLoader(['entries' => []]);

    $config = makeConfig(['paginate' => '5', 'from' => 'articles']);
    $result = makeEndless($config)->exposeAntlersData();

    expect($result['paginate']['has_more_pages'])->toBeFalse();
});

it('includes current_page, items_per_page, has_more_pages and null totals in paginate key', function () {
    mockLoader(['entries' => ['a', 'b']]);

    $config = makeConfig(['paginate' => '5', 'from' => 'articles']);
    $result = makeEndless($config, page: 2)->exposeAntlersData();

    expect($result['paginate'])->toBe([
        'current_page'   => 2,
        'items_per_page' => 5,
        'has_more_pages' => false,
        'total_items'    => null,
        'total_pages'    => null,
    ]);
});

it('uses a custom as key to find and slice paginated items', function () {
    mockLoader(['articles' => ['x', 'y', 'z', 'extra']]);

    $config = makeConfig(['paginate' => '3', 'as' => 'articles', 'from' => 'articles']);
    $result = makeEndless($config)->exposeAntlersData();

    expect($result['paginate']['has_more_pages'])->toBeTrue()
        ->and(count($result['articles']))->toBe(3)
        ->and(isset($result['entries']))->toBeFalse();
});

it('defaults to entries key when as param is not set', function () {
    mockLoader(['entries' => ['a', 'b', 'c', 'd']]);

    $config = makeConfig(['paginate' => '3', 'from' => 'articles']);
    $result = makeEndless($config)->exposeAntlersData();

    expect($result)->toHaveKey('entries')
        ->and(count($result['entries']))->toBe(3);
});

it('returns empty array from alpineData when paginate param is not set', function () {
    $config = makeConfig(['from' => 'articles']);
    $component = makeEndless($config);

    $alpineData = $component->exposeAlpineData(['entries' => []]);

    expect($alpineData)->toBe([]);
});

it('alpineData uses has_more_pages from paginate result directly', function () {
    $config = makeConfig(['paginate' => '5', 'from' => 'articles']);
    $component = makeEndless($config);

    $antlersData = [
        'paginate' => [
            'current_page'   => 1,
            'items_per_page' => 5,
            'has_more_pages' => true,
            'total_items'    => null,
            'total_pages'    => null,
        ],
    ];

    $alpineData = $component->exposeAlpineData($antlersData);

    expect($alpineData['paginate']['has_more_pages'])->toBeTrue()
        ->and($alpineData['paginate']['current_page'])->toBe(1)
        ->and($alpineData['paginate']['items_per_page'])->toBe(5);
});

it('alpineData sets has_more_pages false when it is false in paginate result', function () {
    $config = makeConfig(['paginate' => '5', 'from' => 'articles']);
    $component = makeEndless($config);

    $antlersData = [
        'paginate' => [
            'current_page'   => 1,
            'items_per_page' => 5,
            'has_more_pages' => false,
            'total_items'    => null,
            'total_pages'    => null,
        ],
    ];

    $alpineData = $component->exposeAlpineData($antlersData);

    expect($alpineData['paginate']['has_more_pages'])->toBeFalse();
});

it('alpineData falls back to total_pages > current_page when has_more_pages is absent', function () {
    $config = makeConfig(['paginate' => '5', 'from' => 'articles']);
    $component = makeEndless($config);

    // Simulate a legacy paginate array without has_more_pages (e.g. from external tag)
    $antlersData = [
        'paginate' => [
            'current_page'   => 2,
            'items_per_page' => 5,
            'total_items'    => 30,
            'total_pages'    => 6,
        ],
    ];

    $alpineData = $component->exposeAlpineData($antlersData);

    // total_pages (6) > current_page (2) => true
    expect($alpineData['paginate']['has_more_pages'])->toBeTrue();
});

it('alpineData falls back correctly when on last page (total_pages == current_page)', function () {
    $config = makeConfig(['paginate' => '5', 'from' => 'articles']);
    $component = makeEndless($config);

    $antlersData = [
        'paginate' => [
            'current_page'   => 6,
            'items_per_page' => 5,
            'total_items'    => 30,
            'total_pages'    => 6,
        ],
    ];

    $alpineData = $component->exposeAlpineData($antlersData);

    // total_pages (6) > current_page (6) => false
    expect($alpineData['paginate']['has_more_pages'])->toBeFalse();
});
