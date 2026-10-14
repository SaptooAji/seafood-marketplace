@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'disabled' => false])

{{--
    A pill button: primary (mangrove green, one per screen), secondary (ink outline),
    or ghost (green text). With an href it is a link; a disabled link loses its
    href and is marked aria-disabled.
--}}
@php
    $variants = [
        'primary' => 'bg-primary-700 text-white hover:bg-primary-800',
        'secondary' => 'border-2 border-ink text-ink hover:bg-subtle',
        'ghost' => 'text-primary-700 hover:underline',
    ];
    $classes = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold transition-colors focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 '
        .($disabled ? 'cursor-not-allowed bg-subtle text-muted' : ($variants[$variant] ?? $variants['primary']));
@endphp

@if ($href !== null && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@elseif ($href !== null)
    <a aria-disabled="true" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
