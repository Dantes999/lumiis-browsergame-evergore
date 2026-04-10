@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'level' => 'h1',
])

@php
    $headingTag = in_array($level, ['h1', 'h2', 'h3'], true) ? $level : 'h1';
@endphp

<div {{ $attributes->merge(['class' => 'theme-page-heading']) }}>
    @if ($eyebrow)
        <p class="theme-page-eyebrow">{{ $eyebrow }}</p>
    @endif

    <{{ $headingTag }} class="theme-page-title">{{ $title }}</{{ $headingTag }}>

    @if ($description)
        <p class="theme-page-description">{{ $description }}</p>
    @endif

    {{ $slot }}
</div>
