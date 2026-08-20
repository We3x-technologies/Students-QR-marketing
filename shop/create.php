<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/jwt.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    response(
        false,
        'Only POST method is allowed.',
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
// REQUIRED FIELDS
// ============================================================

$required_fields = [
    'shop_name'  => 'Shop name',
    'owner_name' => 'Owner name',
    'phone'      => 'Phone number',
    'email'      => 'Email address',
    'address'    => 'Address',
    'username'   => 'Username',
    'password'   => 'Password'
];

foreach ($required_fields as $field => $label) {

    if (
        !array_key_exists($field, $input) ||
        !is_string($input[$field]) ||
        trim($input[$field]) === ''
    ) {

        response(
            false,
            $label . ' is required.',
            [],
            422
        );
    }
}


// ============================================================
// GET INPUT
// ============================================================

$shop_name  = trim($input['shop_name']);
$owner_name = trim($input['owner_name']);
$phone      = trim($input['phone']);
$email      = strtolower(trim($input['email']));
$address    = trim($input['address']);
$username   = trim($input['username']);
$password   = $input['password'];


// ============================================================
// SHOP NAME VALIDATION
// ============================================================

if (strlen($shop_name) < 2 || strlen($shop_name) > 150) {

    response(
        false,
        'Shop name must be between 2 and 150 characters.',
        [],
        422
    );
}

if (preg_match('/\s{2,}/', $shop_name)) {

    response(
        false,
        'Shop name cannot contain multiple consecutive spaces.',
        [],
        422
    );
}


// ============================================================
// OWNER NAME VALIDATION
// ============================================================

if (strlen($owner_name) < 2 || strlen($owner_name) > 100) {

    response(
        false,
        'Owner name must be between 2 and 100 characters.',
        [],
        422
    );
}

if (!preg_match('/^[A-Za-z .]+$/', $owner_name)) {

    response(
        false,
        'Owner name can contain only letters, spaces and dots.',
        [],
        422
    );
}

if (preg_match('/\s{2,}/', $owner_name)) {

    response(
        false,
        'Owner name cannot contain multiple consecutive spaces.',
        [],
        422
    );
}


// ============================================================
// PHONE VALIDATION
// ============================================================

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {

    response(
        false,
        'Enter a valid 10-digit Indian mobile number.',
        [],
        422
    );
}

if (preg_match('/^(\d)\1{9}$/', $phone)) {

    response(
        false,
        'Invalid phone number.',
        [],
        422
    );
}


// ============================================================
// EMAIL VALIDATION
// ============================================================

if (strlen($email) > 150) {

    response(
        false,
        'Email address cannot exceed 150 characters.',
        [],
        422
    );
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    response(
        false,
        'Enter a valid email address.',
        [],
        422
    );
}


// ============================================================
// ADDRESS VALIDATION
// ============================================================

if (strlen($address) < 5 || strlen($address) > 1000) {

    response(
        false,
        'Address must be between 5 and 1000 characters.',
        [],
        422
    );
}

if (preg_match('/\s{2,}/', $address)) {

    response(
        false,
        'Address cannot contain multiple consecutive spaces.',
        [],
        422
    );
}


// ============================================================
// USERNAME VALIDATION
// ============================================================

if (strlen($username) < 3 || strlen($username) > 100) {

    response(
        false,
        'Username must be between 3 and 100 characters.',
        [],
        422
    );
}

if (!preg_match('/^[A-Za-z0-9._@-]+$/', $username)) {

    response(
        false,
        'Username can contain only letters, numbers, dot, underscore, @ and hyphen.',
        [],
        422
    );
}


// ============================================================
// PASSWORD VALIDATION
// ============================================================

if (strlen($password) < 8 || strlen($password) > 255) {

    response(
        false,
        'Password must be between 8 and 255 characters.',
        [],
        422
    );
}

if (!preg_match('/[A-Z]/', $password)) {

    response(
        false,
        'Password must contain at least one uppercase letter.',
        [],
        422
    );
}

if (!preg_match('/[a-z]/', $password)) {

    response(
        false,
        'Password must contain at least one lowercase letter.',
        [],
        422
    );
}

if (!preg_match('/[0-9]/', $password)) {

    response(
        false,
        'Password must contain at least one number.',
        [],
        422
    );
}

if (!preg_match('/[^A-Za-z0-9]/', $password)) {

    response(
        false,
        'Password must contain at least one special character.',
        [],
        422
    );
}


// ============================================================
// DUPLICATE PHONE CHECK
// ============================================================

$stmt = $pdo->prepare("
    SELECT id
    FROM shops
    WHERE phone = ?
    LIMIT 1
");

$stmt->execute([
    $phone
]);

if ($stmt->fetch()) {

    response(
        false,
        'Shop phone number already exists.',
        [],
        409
    );
}


// ============================================================
// DUPLICATE USERNAME CHECK
// ============================================================

$stmt = $pdo->prepare("
    SELECT id
    FROM shops
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([
    $username
]);

if ($stmt->fetch()) {

    response(
        false,
        'Shop username already exists.',
        [],
        409
    );
}


// ============================================================
// PASSWORD HASH
// ============================================================

$password_hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

if ($password_hash === false) {

    response(
        false,
        'Password hashing failed.',
        [],
        500
    );
}


// ============================================================
// CREATE SHOP
// ============================================================

$stmt = $pdo->prepare("
    INSERT INTO shops (
        shop_name,
        owner_name,
        phone,
        email,
        address,
        username,
        password,
        status
    )
    VALUES (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        'active'
    )
");

$stmt->execute([
    $shop_name,
    $owner_name,
    $phone,
    $email,
    $address,
    $username,
    $password_hash
]);


// ============================================================
// GET CREATED SHOP
// ============================================================

$shop_id = (int) $pdo->lastInsertId();

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
// DATABASE INSERT VALIDATION
// ============================================================

if (!$shop) {

    response(
        false,
        'Shop created but failed to retrieve shop details.',
        [],
        500
    );
}


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Shop created successfully.',
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
    201
);