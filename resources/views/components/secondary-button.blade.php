<button {{ $attributes->merge(['type' => 'button', 'class' => 'theme-button theme-button-secondary theme-secondary-button']) }}>
    {{ $slot }}
</button>
