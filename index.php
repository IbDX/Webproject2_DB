<?php
/**
 * Backend API entry point.
 */

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

define('APP_ROOT', dirname(__FILE__));
define('APP_ENV', getenv('APP_ENV') ?: 'production');

require_once APP_ROOT . '/src/utils/Response.php';

Response::applyCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
