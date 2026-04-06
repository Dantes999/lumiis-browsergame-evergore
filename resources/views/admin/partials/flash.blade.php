@if (session('status'))
    <div class="theme-status-flash theme-status-success mb-6">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="theme-status-flash theme-status-danger mb-6">
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
