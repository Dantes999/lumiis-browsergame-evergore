@php
    $statusCode = 403;
    $statusTitle = __('Forbidden');
    $statusMessage = __('This route is locked for your current account or role. If you were trying to enter an admin area, return to the realm or profile pages instead.');
@endphp

@include('errors.game')
