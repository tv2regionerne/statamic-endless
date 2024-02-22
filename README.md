# Statamic Endless

Statamic Endless is a Statamic addon that allows you to create "infinite scroll" elements that automatically load new entries when you get to the end of the list.

## How to Install

You can search for this addon in the `Tools > Addons` section of the Statamic control panel and click **install**, or run the following command from your project root:

``` bash
composer require tv2regionerne/statamic-endless
```

## How to Use

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
