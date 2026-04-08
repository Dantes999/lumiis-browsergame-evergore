<?php

$requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicPath = __DIR__.'/../public';
$requestedFile = $publicPath.$requestPath;

if ($requestPath !== '/' && is_file($requestedFile)) {
    return false;
}

require $publicPath.'/index.php';
