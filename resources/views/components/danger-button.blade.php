<button {{ $attributes->merge(['type' => 'submit', 'class' => 'theme-button theme-button-danger theme-danger-button']) }}>
    {{ $slot }}
</button>
