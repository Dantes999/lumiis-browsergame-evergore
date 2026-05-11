@extends('layouts.bootstrap')

@section('content')
    <div class="vh-center">
        <div class="w-100 text-center">
            <h1 class="display-6">Welcome to {{ config('app.name', 'lumiis') }}</h1>
            <p class="lead">A lightweight rebuild using Bootstrap — login or register to continue.</p>

            <div class="d-flex justify-content-center gap-2 mt-3">
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="btn btn-primary">{{ __('Login') }}</a>
                @endif

                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn btn-outline-secondary">{{ __('Register') }}</a>
                @endif
            </div>
        </div>
    </div>
@endsection
