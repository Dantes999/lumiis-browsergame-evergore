@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'theme-input-error space-y-1 text-sm']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
