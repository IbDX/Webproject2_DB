<?php
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('FRONTEND_ORIGIN') ?: '')));
$isDevelopment = getenv('APP_ENV') === 'development';
$isLocalDevOrigin = $requestOrigin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$#', $requestOrigin) === 1;

if ($requestOrigin !== '' && ($isDevelopment || $isLocalDevOrigin || in_array($requestOrigin, $allowedOrigins, true))) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, ngrok-skip-browser-warning');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}

/**
 * Backend API entry point.
 */

define('APP_ROOT', dirname(__FILE__));
define('APP_ENV', getenv('APP_ENV') ?: 'production');

if (getenv('APP_ENV') === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

require_once APP_ROOT . '/src/utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = $path === '' ? [] : explode('/', $path);

if (!in_array('api', $segments, true)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Backend API only'
    ]);
    exit;
}

require APP_ROOT . '/src/controllers/Router.php';
