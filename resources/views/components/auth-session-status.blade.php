@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'theme-status-flash theme-status-success']) }}>
        {{ $status }}
    </div>
@endif
