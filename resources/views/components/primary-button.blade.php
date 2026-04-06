<button {{ $attributes->merge(['type' => 'submit', 'class' => 'theme-button theme-button-primary theme-primary-button']) }}>
    {{ $slot }}
</button>
