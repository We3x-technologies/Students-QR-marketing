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

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {

    response(
        false,
        'Only DELETE method is allowed.',
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
// JSON INPUT
// ============================================================

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {

    response(
        false,
        'Invalid JSON request.',
        [],
        400
    );
}


// ============================================================
// SHOP ID REQUIRED
// ============================================================

if (
    !array_key_exists('shop_id', $input) ||
    $input['shop_id'] === '' ||
    $input['shop_id'] === null
) {

    response(
        false,
        'Shop ID is required.',
        [],
        422
    );
}


// ============================================================
// SHOP ID VALIDATION
// ============================================================

$shop_id = filter_var(
    $input['shop_id'],
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($shop_id === false) {

    response(
        false,
        'Invalid Shop ID.',
        [],
        422
    );
}

$shop_id = (int) $shop_id;


// ============================================================
// GET SHOP DETAILS BEFORE DELETE
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
// SHOP EXISTENCE VALIDATION
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
// DELETE SHOP
// ============================================================

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        DELETE FROM shops
        WHERE id = ?
    ");

    $stmt->execute([
        $shop_id
    ]);


    // ========================================================
    // DELETE VALIDATION
    // ========================================================

    if ($stmt->rowCount() !== 1) {

        $pdo->rollBack();

        response(
            false,
            'Shop could not be deleted.',
            [],
            500
        );
    }


    $pdo->commit();


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }

    response(
        false,
        'Shop deletion failed.',
        [],
        500
    );
}


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Shop deleted successfully.',
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