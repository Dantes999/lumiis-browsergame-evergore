@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

<div class="js-modal fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
     data-modal-name="{{ $name }}"
     style="display: {{ $show ? 'block' : 'none' }};">

    <div class="js-modal-backdrop fixed inset-0 transform transition-all" data-modal-backdrop>
        <div class="absolute inset-0 bg-[rgba(10,4,6,0.82)] backdrop-blur-sm"></div>
    </div>

    <div class="mb-6 overflow-hidden rounded-[1.8rem] border border-amber-200/20 bg-[linear-gradient(180deg,rgba(58,22,26,0.97),rgba(20,10,13,0.98))] shadow-2xl shadow-black/50 transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto js-modal-window">
        {{ $slot }}
    </div>
</div>
