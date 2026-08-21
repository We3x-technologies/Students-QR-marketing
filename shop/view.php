<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
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


// ============================================================
// RESPONSE
// ============================================================

function response($success, $message, $data = [], $status_code = 200)
{
    http_response_code($status_code);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// ============================================================
// REQUEST METHOD
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    response(
        false,
        'Only GET method is allowed.',
        [],
        405
    );
}


// ============================================================
// AUTHORIZATION HEADER
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
// ADMIN ROLE CHECK
// ============================================================

if (
    !isset($payload['role']) ||
    $payload['role'] !== ADMIN_ROLE
) {

    response(
        false,
        'Admin access required.',
        [],
        403
    );
}


// ============================================================
// SHOP ID VALIDATION
// ============================================================

if (
    !isset($_GET['shop_id']) ||
    trim($_GET['shop_id']) === ''
) {

    response(
        false,
        'Shop ID is required.',
        [],
        422
    );
}


$shop_id = trim($_GET['shop_id']);


// ============================================================
// SHOP ID FORMAT VALIDATION
// ============================================================

if (!ctype_digit($shop_id)) {

    response(
        false,
        'Shop ID must be a valid positive integer.',
        [],
        422
    );
}


$shop_id = (int) $shop_id;


if ($shop_id < 1) {

    response(
        false,
        'Shop ID must be greater than zero.',
        [],
        422
    );
}


// ============================================================
// FETCH SHOP
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        shop_name,
        owner_name,
        phone,
        email,
        address,
        username,
        qr_code,
        status,
        last_login_at,
        created_at,
        updated_at
    FROM shops
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $shop_id
]);

$shop = $stmt->fetch();


// ============================================================
// SHOP NOT FOUND
// ============================================================

if (!$shop) {

    response(
        false,
        'Shop not found.',
        [],
        404
    );
}


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Shop details fetched successfully.',
    [
        'shop' => [
            'id'            => (int) $shop['id'],
            'shop_name'     => $shop['shop_name'],
            'owner_name'    => $shop['owner_name'],
            'phone'         => $shop['phone'],
            'email'         => $shop['email'],
            'address'       => $shop['address'],
            'username'      => $shop['username'],
            'qr_code'       => $shop['qr_code'],
            'status'        => $shop['status'],
            'last_login_at' => $shop['last_login_at'],
            'created_at'    => $shop['created_at'],
            'updated_at'    => $shop['updated_at']
        ]
    ],
    200
);