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
// INPUT
// ============================================================

$user_id = isset($input['user_id'])
    ? trim($input['user_id'])
    : '';

$password = isset($input['password'])
    ? $input['password']
    : '';


// ============================================================
// VALIDATION
// ============================================================

if ($user_id === '') {

    response(
        false,
        'User ID is required.',
        [],
        422
    );
}

if (strlen($user_id) < 3 || strlen($user_id) > 100) {

    response(
        false,
        'Invalid User ID.',
        [],
        422
    );
}

if (!preg_match('/^[A-Za-z0-9._@-]+$/', $user_id)) {

    response(
        false,
        'Invalid User ID format.',
        [],
        422
    );
}

if ($password === '') {

    response(
        false,
        'Password is required.',
        [],
        422
    );
}


// ============================================================
// ADMIN LOGIN CHECK
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        username,
        password,
        status
    FROM admins
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$user_id]);

$admin = $stmt->fetch();


// ============================================================
// ADMIN LOGIN
// ============================================================

if ($admin) {

    if ($admin['status'] !== 'active') {

        response(
            false,
            'Admin account is inactive.',
            [],
            403
        );
    }

    if (!password_verify($password, $admin['password'])) {

        response(
            false,
            'Invalid User ID or password.',
            [],
            401
        );
    }

    $token = generateToken(
        $admin['id'],
        $admin['username'],
        ADMIN_ROLE
    );

    $stmt = $pdo->prepare("
        UPDATE admins
        SET last_login_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $admin['id']
    ]);

    response(
        true,
        'Login successful.',
        [
            'token' => $token,
            'user' => [
                'id'       => (int) $admin['id'],
                'username' => $admin['username'],
                'role'     => ADMIN_ROLE
            ]
        ]
    );
}


// ============================================================
// SHOP OWNER LOGIN CHECK
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        shop_name,
        owner_name,
        username,
        password,
        status
    FROM shops
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$user_id]);

$shop = $stmt->fetch();


// ============================================================
// SHOP OWNER LOGIN
// ============================================================

if ($shop) {

    if ($shop['status'] !== 'active') {

        response(
            false,
            'Shop account is inactive.',
            [],
            403
        );
    }

    if (!password_verify($password, $shop['password'])) {

        response(
            false,
            'Invalid User ID or password.',
            [],
            401
        );
    }

    $token = generateToken(
        $shop['id'],
        $shop['username'],
        SHOP_ROLE
    );

    $stmt = $pdo->prepare("
        UPDATE shops
        SET last_login_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $shop['id']
    ]);

    response(
        true,
        'Login successful.',
        [
            'token' => $token,
            'user' => [
                'id'       => (int) $shop['id'],
                'username' => $shop['username'],
                'role'     => SHOP_ROLE
            ]
        ]
    );
}


// ============================================================
// INVALID LOGIN
// ============================================================

response(
    false,
    'Invalid User ID or password.',
    [],
    401
);