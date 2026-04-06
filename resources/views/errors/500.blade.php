@php
    $statusCode = 500;
    $statusTitle = __('Server Error');
    $statusMessage = __('The realm encountered an internal problem while preparing this page. Try again in a moment or move back to a safer route first.');
@endphp

@include('errors.game')
