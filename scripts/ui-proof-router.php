<?php

$requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicPath = __DIR__.'/../public';
$requestedFile = $publicPath.$requestPath;

if ($requestPath !== '/' && is_file($requestedFile)) {
    return false;
}

// The built-in server points SCRIPT_NAME at the requested SPA path. Laravel's
// path detection then sees the wrong script and returns 404 for deep links.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $publicPath.'/index.php';
