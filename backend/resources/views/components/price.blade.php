@props(['amount', 'unit' => null])

{{-- A price in rupiah ("Rp45.000"), optionally followed by its unit ("/ kg"). Prices are whole rupiah. --}}
<span {{ $attributes->merge(['class' => 'whitespace-nowrap']) }}><span class="font-extrabold tracking-tight">Rp{{ number_format((int) $amount, 0, ',', '.') }}</span>@if (filled($unit))<span class="text-xs font-medium tracking-normal text-muted"> / {{ $unit }}</span>@endif</span>
