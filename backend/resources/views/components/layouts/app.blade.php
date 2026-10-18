<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $fullTitle() }}</title>
    <meta name="description" content="{{ $metaDescription() }}">
    @unless ($bare)
        <link rel="canonical" href="{{ $canonicalUrl() }}">
    @endunless
    @if ($noindex)
        <meta name="robots" content="noindex, follow">
    @endif

    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $fullTitle() }}">
    <meta property="og:description" content="{{ $metaDescription() }}">
    @unless ($bare)
        <meta property="og:url" content="{{ $canonicalUrl() }}">
    @endunless
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
    <a href="#konten" class="sr-only rounded-full bg-ink px-4 py-2 font-bold text-white focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50">Lewati ke konten</a>
    @php($categories = $rootCategories())

    <header class="border-b border-line bg-white print:hidden">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3">
            <x-logo />

            <form action="{{ route('catalog') }}" method="GET" role="search" class="order-last flex h-12 w-full items-center gap-2 rounded-full border-[1.5px] border-muted bg-surface pr-1.5 pl-4 focus-within:border-primary-600 sm:order-0 sm:w-auto sm:flex-1">
                <label for="search" class="sr-only">Cari produk</label>
                <svg class="size-4.5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input
                    id="search"
                    type="search"
                    name="q"
                    value="{{ $searchQuery() }}"
                    placeholder="Cari kepiting, cumi, kerang"
                    maxlength="{{ \App\Livewire\Catalog::MAX_QUERY_LENGTH }}"
                    class="min-w-0 flex-1 bg-transparent text-base text-ink placeholder:text-muted focus:outline-none"
                >
                <button type="submit" class="h-9 shrink-0 rounded-full bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800">Cari</button>
            </form>

            <nav aria-label="Utama" class="ml-auto flex items-center gap-5 text-sm font-semibold sm:ml-0">
                <a href="{{ route('catalog') }}" class="hover:text-primary-700">Katalog</a>
                <a href="{{ route('recipes.index') }}" class="hover:text-primary-700">Resep</a>
                {{-- The bare layout skips the auth check: a signed-in session would query the users table. --}}
                @if (! $bare && auth()->guest())
                    <a href="{{ route('login') }}" class="flex h-11 items-center rounded-full border-[1.5px] border-ink px-4 hover:bg-surface">Masuk</a>
                @endif
            </nav>
        </div>

    </header>

    <main id="konten" @class(['w-full flex-1', 'mx-auto max-w-6xl px-4 py-6' => ! $fullWidth])>
        {{ $slot }}
    </main>

    <footer class="bg-ink text-white print:hidden">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:flex-row sm:justify-between">
            <div class="flex max-w-sm flex-col gap-2">
                <span class="text-lg font-extrabold tracking-tight">{{ config('app.name') }}</span>
                <p class="text-sm text-white/80">Hasil laut segar dari lapak terdekat, dengan stok yang dicek setiap pagi.</p>
            </div>
            <nav aria-label="Tautan bawah" class="flex flex-wrap gap-x-10 gap-y-4 text-sm">
                <ul class="flex flex-col gap-2">
                    <li><a href="{{ route('catalog') }}" class="hover:underline">Katalog</a></li>
                    @foreach ($categories as $category)
                        <li><a href="{{ route('catalog', ['kategori' => $category->slug]) }}" class="hover:underline">{{ $category->name }}</a></li>
                    @endforeach
                </ul>
                <ul class="flex flex-col gap-2">
                    <li><a href="{{ route('recipes.index') }}" class="hover:underline">Semua resep</a></li>
                    <li><a href="{{ url('/seller') }}" class="hover:underline">Buka lapak</a></li>
                    <li><a href="{{ route('credits') }}" class="hover:underline">Kredit foto</a></li>
                </ul>
            </nav>
        </div>
        <p class="mx-auto max-w-6xl border-t border-white/15 px-4 py-4 text-xs text-white/70">&copy; {{ now()->year }} {{ config('app.name') }}</p>
    </footer>

    @livewireScripts
</body>
</html>
