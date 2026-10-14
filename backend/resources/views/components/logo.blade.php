{{-- The mark from public/favicon.svg beside the app name; the name is the link's text. --}}
<a href="{{ url('/') }}" {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false" class="size-9 shrink-0">
        <path d="M22 4H42A18 18 0 0 1 60 22V42A18 18 0 0 1 42 60H22A18 18 0 0 1 4 42V22A18 18 0 0 1 22 4Z" fill="#FFFFFF" stroke="#1B2E25" stroke-width="3"/>
        <path d="M14 33C19 26 26 24 34 26L41 21V45L34 40C26 42 19 40 14 33Z" fill="none" stroke="#1B2E25" stroke-width="3" stroke-linejoin="round"/>
        <circle cx="48" cy="15" r="6" fill="#D9733C"/>
    </svg>
    <span class="text-xl font-extrabold tracking-tight text-ink">{{ config('app.name') }}</span>
</a>
