<?php

require_once __DIR__ . '/../config/jwt.php';

// ============================================================
// CORS
// ============================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');


// ============================================================
// CORS PREFLIGHT REQUEST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(204);

    exit;
}

header('Content-Type: application/json; charset=UTF-8');


function response($success, $message, $data = [], $status_code = 200)
{
    http_response_code($status_code);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ]);

    exit;
}


// ============================================================
// REQUEST METHOD
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    response(
        false,
        'Only POST method is allowed.',
        [],
        405
    );
}


// ============================================================
// GET AUTHORIZATION HEADER
// ============================================================

$headers = function_exists('getallheaders')
    ? getallheaders()
    : [];

$authorization = '';

foreach ($headers as $key => $value) {

    if (strtolower($key) === 'authorization') {

        $authorization = trim($value);

        break;
    }
}


// ============================================================
// TOKEN VALIDATION
// ============================================================

if ($authorization === '') {

    response(
        false,
        'Authorization token is required.',
        [],
        401
    );
}


if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {

    response(
        false,
        'Invalid authorization format.',
        [],
        401
    );
}


$token = trim($matches[1]);


// ============================================================
// VERIFY TOKEN
// ============================================================

$payload = verifyToken($token);

if ($payload === false) {

    response(
        false,
        'Invalid or expired token.',
        [],
        401
    );
}


// ============================================================
// LOGOUT SUCCESS
// ============================================================

response(
    true,
    'Logout successful.'
);