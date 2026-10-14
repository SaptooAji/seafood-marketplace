<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $fullTitle() }}</title>
    <meta name="description" content="{{ $metaDescription() }}">
    <link rel="canonical" href="{{ $canonicalUrl() }}">
    @if ($noindex)
        <meta name="robots" content="noindex, follow">
    @endif

    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $fullTitle() }}">
    <meta property="og:description" content="{{ $metaDescription() }}">
    <meta property="og:url" content="{{ $canonicalUrl() }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    {{-- primary-700 in resources/css/app.css. --}}
    <meta name="theme-color" content="#2F5D46">

    @stack('head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
    <header class="border-b border-line bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
            <x-logo />

            <form action="{{ route('catalog') }}" method="GET" role="search" class="order-last w-full sm:order-none sm:flex-1">
                <label for="search" class="sr-only">Cari produk</label>
                <input
                    id="search"
                    type="search"
                    name="q"
                    value="{{ $searchQuery() }}"
                    placeholder="Cari kepiting, cumi, tiram..."
                    maxlength="{{ \App\Livewire\Catalog::MAX_QUERY_LENGTH }}"
                    class="w-full rounded-lg border border-line-strong px-3 py-2 text-sm focus:border-primary-600 focus:outline-none"
                >
            </form>
        </div>

        @php($categories = $rootCategories())
        @if ($categories->isNotEmpty())
            <nav aria-label="Kategori" class="border-t border-line">
                <ul class="mx-auto flex max-w-6xl gap-4 overflow-x-auto px-4 py-2 text-sm">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('catalog', ['kategori' => $category->slug]) }}" class="whitespace-nowrap text-muted hover:text-primary-700">{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="border-t border-line bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-muted">
            &copy; {{ now()->year }} {{ config('app.name') }}. Hasil laut segar langsung dari penjual.
        </div>
    </footer>

    @livewireScripts
</body>
</html>
