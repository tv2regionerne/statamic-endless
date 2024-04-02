# Statamic Endless

Statamic Endless allows you to create infinite scroll lists that automatically or manually load new entries when you get to the end.

## How to Install

You can search for this addon in the `Tools > Addons` section of the Statamic control panel and click **install**, or run the following command from your project root:

``` bash
composer require tv2regionerne/statamic-endless
```

## How to Use

Make sure Livewire v3 is installed, then use the `collection:endless` tag:

```antlers
{{ collection:endless as="posts" from="blog" paginate="5" }}
    <div x-ref="append">
        {{ posts }}
            {{ partial:blog/post }}
        {{ /posts }}
    </div>
    <button x-on:click="trigger" x-show="paginate.has_more_pages">Load More</button>
{{ /collection:endless }}
```

Example with intersectors:
```antlers
{{ collection:endless as="posts" from="blog" paginate="5" }}
    <div x-ref="append">
        {{ posts }}
            {{ partial:blog/post }}
        {{ /posts }}
    </div>
    <div
    x-data="{
        intersecting: false,
        init() {
            $watch('intersecting', () => this.check());
        },
        check() {
            if (this.intersecting && paginate.has_more_pages) {
                trigger().then(() => this.check());
            }
        },
    }"
    x-show="!loading && paginate.has_more_pages"
    x-intersect:enter="intersecting = true"
    x-intersect:leave="intersecting = false">
</div>
{{ /collection:endless }}
```


You must enable pagination.

The content will be wrapped in a Livewire/Alpine component:

* You should add an `x-ref` of either `append` or `prepend` to the element that contains your list.
* You can call `trigger` to load more entries using `x-intersect` or `x-on`.
* You can check the loading state with `loading`.

On secondary loads variables from outside tag scope will only be avaliable if you list them in the `context` parameter (pipe delimited). These variables must be serializable.
