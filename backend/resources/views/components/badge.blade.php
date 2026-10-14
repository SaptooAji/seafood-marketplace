@props(['variant' => 'neutral'])

{{--
    A small pill label: neutral (condition), ink (stock checked at a time),
    success (ready to ship), warning (stock not confirmed, crab orange), or danger (out of stock).
--}}
@php
    $variants = [
        'neutral' => 'bg-subtle text-ink',
        'ink' => 'bg-ink text-white',
        'success' => 'bg-success-50 text-primary-800',
        'warning' => 'bg-warning-50 text-warning-700',
        'danger' => 'bg-danger-50 text-danger-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold '.($variants[$variant] ?? $variants['neutral'])]) }}>{{ $slot }}</span>
