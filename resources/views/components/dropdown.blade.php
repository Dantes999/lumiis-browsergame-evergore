@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'theme-dropdown-panel'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="dropdown" data-dropdown>
    <div class="dropdown-toggle" data-dropdown-toggle role="button" aria-expanded="false">
        {{ $trigger }}
    </div>

    <div class="dropdown-menu {{ $alignmentClasses }} mt-2 {{ $width }} shadow-lg" data-dropdown-menu style="display: none;">
        <div class="overflow-hidden {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
