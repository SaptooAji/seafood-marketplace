{{-- An empty href loads the same address again without printing it in the page. --}}
<div class="mt-1 flex flex-wrap gap-2.5">
    <a href="" class="inline-flex h-13 items-center rounded-full bg-primary-700 px-6 font-bold text-white hover:bg-primary-800 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $reload }}</a>
    <a href="{{ url('/') }}" class="inline-flex h-13 items-center rounded-full border-2 border-ink px-6 font-bold text-ink hover:bg-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Kembali ke beranda</a>
</div>
