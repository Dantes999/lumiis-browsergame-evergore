@props(['active'])

@php
$classes = ($active ?? false)
            ? 'theme-button theme-button-primary theme-button-compact theme-button-active'
            : 'theme-button theme-button-primary theme-button-compact';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
