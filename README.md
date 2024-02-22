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
      <button x-on:click="trigger">Load More</button>
{{ /collection:endless }}
```

The content will be wrapped in a Livewire/Alpine component that you can interact with via Alpine properties.

The element containing your entries should have an `x-ref` of either `append` or `prepend` depending on where you want new entries added.

You can call `trigger` to load more entries using `x-intersect` or `x-on`.

You can check the loading state with `loading`.
