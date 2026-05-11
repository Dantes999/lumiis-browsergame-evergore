<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'lumiis') }}</title>

    <!-- Include app CSS/JS (vite-built) with a Bootstrap-only JS entry (no Alpine) -->
    @vite(['resources/css/app.css', 'resources/js/bootstrap-only.js'])

    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="" crossorigin="anonymous">

    <style>
      /* small utility to center the content */
      .vh-center { min-height: 60vh; display: flex; align-items: center; }
    </style>
  </head>
  <body>
    <nav class="dashboard-classic-header-wrapper navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="/">{{ config('app.name', 'lumiis') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">
            @guest
              <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a></li>
              <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a></li>
            @else
              <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
              <li class="nav-item">
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-link nav-link">{{ __('Logout') }}</button></form>
              </li>
            @endguest
          </ul>
        </div>
      </div>
    </nav>

    <main class="container py-4" style="padding-top: var(--dashboard-header-height, 0px);">
      @yield('content')
    </main>

    <!-- Bootstrap JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="" crossorigin="anonymous"></script>
  </body>
</html>
