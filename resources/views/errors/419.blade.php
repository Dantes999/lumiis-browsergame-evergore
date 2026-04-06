@php
    $statusCode = 419;
    $statusTitle = __('Session Expired');
    $statusMessage = __('Your session token expired before the request completed. Refresh the page, sign in again if needed, and repeat the action from a stable route.');
@endphp

@include('errors.game')
