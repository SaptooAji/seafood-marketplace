{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
    </url>
    <url>
        <loc>{{ route('catalog') }}</loc>
    </url>
    <url>
        <loc>{{ route('credits') }}</loc>
    </url>
@foreach ($products as $product)
    <url>
        <loc>{{ route('products.show', $product->slug) }}</loc>
        <lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>
    </url>
@endforeach
@foreach ($stores as $store)
    <url>
        <loc>{{ route('stores.show', $store->slug) }}</loc>
    </url>
@endforeach
</urlset>
