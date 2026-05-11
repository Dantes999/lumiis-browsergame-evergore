@extends('layouts.bootstrap')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="card-title mb-3">{{ __('Join Lumiis') }}</h3>

                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="character_name" class="form-label">{{ __('Character Name') }}</label>
                            <input id="character_name" type="text" class="form-control @error('character_name') is-invalid @enderror" name="character_name" value="{{ old('character_name') }}" required autofocus>
                            @error('character_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="race" class="form-label">{{ __('Race') }}</label>
                            <select id="race" name="race" class="form-select @error('race') is-invalid @enderror" required>
                                <option value="">{{ __('Choose a race') }}</option>
                                @foreach ($raceOptions as $raceKey => $raceOption)
                                    <option value="{{ $raceKey }}" @selected(old('race', $defaultRace ?? '') === $raceKey)>{{ $raceOption['label'] }}</option>
                                @endforeach
                            </select>
                            @error('race')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Password') }}</label>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
                            <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('login') }}">{{ __('Already registered?') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('Create Your Realm Character') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
