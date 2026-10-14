@props(['name', 'label', 'id' => null, 'type' => 'text', 'help' => null, 'error' => null])

{{--
    A labelled text field. The error comes from the prop or, when none is given,
    from the validation errors for the field's name; help and error are linked
    with aria-describedby.
--}}
@php
    $id ??= $name;
    $error ??= isset($errors) ? $errors->first($name) : null;
    $describedBy = collect([filled($help) ? $id.'-help' : null, filled($error) ? $id.'-error' : null])->filter()->implode(' ');
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-bold text-ink">{{ $label }}</label>
    <input
        type="{{ $type }}"
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if (filled($error)) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'h-12 rounded-full border-[1.5px] bg-white px-4 text-base text-ink placeholder:text-muted focus:border-primary-600 focus:outline-none '.(filled($error) ? 'border-danger-700' : 'border-line-strong')]) }}
    >
    @if (filled($help))
        <p id="{{ $id }}-help" class="text-xs text-muted">{{ $help }}</p>
    @endif
    @if (filled($error))
        <p id="{{ $id }}-error" class="text-xs font-medium text-danger-700">{{ $error }}</p>
    @endif
</div>
