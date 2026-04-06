@props(['active'])

@php
$classes = ($active ?? false)
            ? 'theme-button theme-button-primary theme-button-compact theme-button-active block w-full justify-start text-start'
            : 'theme-button theme-button-primary theme-button-compact block w-full justify-start text-start';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
