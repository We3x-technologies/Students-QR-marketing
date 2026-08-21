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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {

    response(
        false,
        'Only PUT method is allowed.',
        [],
        405
    );
}


// ============================================================
// AUTHORIZATION HEADER
// ============================================================

$authorization = '';

if (function_exists('getallheaders')) {

    $headers = getallheaders();

    foreach ($headers as $key => $value) {

        if (strtolower($key) === 'authorization') {

            $authorization = trim($value);

            break;
        }
    }
}


// ============================================================
// FALLBACK AUTHORIZATION HEADER
// ============================================================

if ($authorization === '') {

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {

        $authorization = trim(
            $_SERVER['HTTP_AUTHORIZATION']
        );

    } elseif (
        isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
    ) {

        $authorization = trim(
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        );
    }
}


// ============================================================
// AUTHORIZATION VALIDATION
// ============================================================

if ($authorization === '') {

    response(
        false,
        'Authorization token is required.',
        [],
        401
    );
}


// ============================================================
// BEARER TOKEN VALIDATION
// ============================================================

if (
    !preg_match(
        '/^Bearer\s+(.+)$/i',
        $authorization,
        $matches
    )
) {

    response(
        false,
        'Invalid authorization format. Use Bearer token.',
        [],
        401
    );
}


$token = trim($matches[1]);


// ============================================================
// TOKEN VALIDATION
// ============================================================

if ($token === '') {

    response(
        false,
        'Bearer token is required.',
        [],
        401
    );
}


try {

    $payload = verifyToken($token);

} catch (Throwable $e) {

    response(
        false,
        'Invalid or expired token.',
        [],
        401
    );
}


if (
    $payload === false ||
    !is_array($payload)
) {

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

$raw_input = file_get_contents('php://input');

$input = json_decode(
    $raw_input,
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
// SHOP ID ONLY REQUIRED
// ============================================================

if (
    !array_key_exists('shop_id', $input) ||
    trim((string) $input['shop_id']) === ''
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
// ALLOWED UPDATE FIELDS
// ============================================================

$allowed_fields = [
    'shop_name',
    'owner_name',
    'phone',
    'email',
    'address',
    'username',
    'password',
    'status'
];


// ============================================================
// CHECK WHETHER ANY FIELD WAS SENT
// ============================================================

$fields_to_update = [];

foreach ($allowed_fields as $field) {

    if (array_key_exists($field, $input)) {

        $fields_to_update[] = $field;
    }
}


if (empty($fields_to_update)) {

    response(
        false,
        'At least one field is required to update.',
        [],
        422
    );
}


// ============================================================
// CHECK SHOP EXISTS
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
        password,
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

$existing_shop = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$existing_shop) {

    response(
        false,
        'Shop not found.',
        [],
        404
    );
}


// ============================================================
// VALIDATED VALUES
// ============================================================

$update_values = [];


// ============================================================
// SHOP NAME
// ============================================================

if (array_key_exists('shop_name', $input)) {

    $shop_name =
        trim((string) $input['shop_name']);


    if (
        strlen($shop_name) < 2 ||
        strlen($shop_name) > 150
    ) {

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


    $update_values['shop_name'] =
        $shop_name;
}


// ============================================================
// OWNER NAME
// ============================================================

if (array_key_exists('owner_name', $input)) {

    $owner_name =
        trim((string) $input['owner_name']);


    if (
        strlen($owner_name) < 2 ||
        strlen($owner_name) > 100
    ) {

        response(
            false,
            'Owner name must be between 2 and 100 characters.',
            [],
            422
        );
    }


    if (
        !preg_match(
            '/^[A-Za-z .]+$/',
            $owner_name
        )
    ) {

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


    $update_values['owner_name'] =
        $owner_name;
}


// ============================================================
// PHONE
// ============================================================

if (array_key_exists('phone', $input)) {

    $phone =
        trim((string) $input['phone']);


    if (
        !preg_match(
            '/^[6-9][0-9]{9}$/',
            $phone
        )
    ) {

        response(
            false,
            'Enter a valid 10-digit Indian mobile number.',
            [],
            422
        );
    }


    if (
        preg_match(
            '/^(\d)\1{9}$/',
            $phone
        )
    ) {

        response(
            false,
            'Invalid phone number.',
            [],
            422
        );
    }


    // --------------------------------------------------------
    // DUPLICATE PHONE
    // --------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT id
        FROM shops
        WHERE phone = ?
        AND id != ?
        LIMIT 1
    ");

    $stmt->execute([
        $phone,
        $shop_id
    ]);


    if ($stmt->fetch()) {

        response(
            false,
            'Shop phone number already exists.',
            [],
            409
        );
    }


    $update_values['phone'] =
        $phone;
}


// ============================================================
// EMAIL
// ============================================================

if (array_key_exists('email', $input)) {

    $email =
        strtolower(
            trim((string) $input['email'])
        );


    if (strlen($email) > 150) {

        response(
            false,
            'Email address cannot exceed 150 characters.',
            [],
            422
        );
    }


    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        response(
            false,
            'Enter a valid email address.',
            [],
            422
        );
    }


    // --------------------------------------------------------
    // DUPLICATE EMAIL
    // --------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT id
        FROM shops
        WHERE email = ?
        AND id != ?
        LIMIT 1
    ");

    $stmt->execute([
        $email,
        $shop_id
    ]);


    if ($stmt->fetch()) {

        response(
            false,
            'Shop email address already exists.',
            [],
            409
        );
    }


    $update_values['email'] =
        $email;
}


// ============================================================
// ADDRESS
// ============================================================

if (array_key_exists('address', $input)) {

    $address =
        trim((string) $input['address']);


    if (
        strlen($address) < 5 ||
        strlen($address) > 1000
    ) {

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


    $update_values['address'] =
        $address;
}


// ============================================================
// USERNAME
// ============================================================

if (array_key_exists('username', $input)) {

    $username =
        trim((string) $input['username']);


    if (
        strlen($username) < 3 ||
        strlen($username) > 100
    ) {

        response(
            false,
            'Username must be between 3 and 100 characters.',
            [],
            422
        );
    }


    if (
        !preg_match(
            '/^[A-Za-z0-9._@-]+$/',
            $username
        )
    ) {

        response(
            false,
            'Username can contain only letters, numbers, dot, underscore, @ and hyphen.',
            [],
            422
        );
    }


    // --------------------------------------------------------
    // DUPLICATE USERNAME
    // --------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT id
        FROM shops
        WHERE username = ?
        AND id != ?
        LIMIT 1
    ");

    $stmt->execute([
        $username,
        $shop_id
    ]);


    if ($stmt->fetch()) {

        response(
            false,
            'Shop username already exists.',
            [],
            409
        );
    }


    $update_values['username'] =
        $username;
}


// ============================================================
// PASSWORD
// ============================================================

if (array_key_exists('password', $input)) {

    $password =
        (string) $input['password'];


    if ($password === '') {

        response(
            false,
            'Password cannot be empty.',
            [],
            422
        );
    }


    if (
        strlen($password) < 8 ||
        strlen($password) > 255
    ) {

        response(
            false,
            'Password must be between 8 and 255 characters.',
            [],
            422
        );
    }


    // --------------------------------------------------------
    // UPPERCASE
    // --------------------------------------------------------

    if (!preg_match('/[A-Z]/', $password)) {

        response(
            false,
            'Password must contain at least one uppercase letter.',
            [],
            422
        );
    }


    // --------------------------------------------------------
    // LOWERCASE
    // --------------------------------------------------------

    if (!preg_match('/[a-z]/', $password)) {

        response(
            false,
            'Password must contain at least one lowercase letter.',
            [],
            422
        );
    }


    // --------------------------------------------------------
    // NUMBER
    // --------------------------------------------------------

    if (!preg_match('/[0-9]/', $password)) {

        response(
            false,
            'Password must contain at least one number.',
            [],
            422
        );
    }


    // --------------------------------------------------------
    // SPECIAL CHARACTER
    // --------------------------------------------------------

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {

        response(
            false,
            'Password must contain at least one special character.',
            [],
            422
        );
    }


    // --------------------------------------------------------
    // HASH PASSWORD
    // --------------------------------------------------------

    $password_hash =
        password_hash(
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


    $update_values['password'] =
        $password_hash;
}


// ============================================================
// STATUS
// ============================================================

if (array_key_exists('status', $input)) {

    $status =
        strtolower(
            trim((string) $input['status'])
        );


    if (
        $status !== 'active' &&
        $status !== 'inactive'
    ) {

        response(
            false,
            'Invalid status. Allowed values are active or inactive.',
            [],
            422
        );
    }


    $update_values['status'] =
        $status;
}


// ============================================================
// NOTHING TO UPDATE AFTER VALIDATION
// ============================================================

if (empty($update_values)) {

    response(
        false,
        'No valid fields available for update.',
        [],
        422
    );
}


// ============================================================
// BUILD DYNAMIC UPDATE QUERY
// ============================================================

$set_parts = [];

$query_values = [];


foreach ($update_values as $column => $value) {

    $set_parts[] =
        $column . ' = ?';

    $query_values[] =
        $value;
}


// ============================================================
// UPDATED AT
// ============================================================

$set_parts[] =
    'updated_at = CURRENT_TIMESTAMP';


// ============================================================
// SHOP ID
// ============================================================

$query_values[] =
    $shop_id;


// ============================================================
// UPDATE DATABASE
// ============================================================

try {

    $sql = "
        UPDATE shops
        SET
            " . implode(', ', $set_parts) . "
        WHERE id = ?
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute(
        $query_values
    );

} catch (PDOException $e) {

    response(
        false,
        'Failed to update shop.',
        [],
        500
    );
}


// ============================================================
// GET UPDATED SHOP
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

$shop =
    $stmt->fetch(PDO::FETCH_ASSOC);


// ============================================================
// UPDATED SHOP VALIDATION
// ============================================================

if (!$shop) {

    response(
        false,
        'Shop updated but failed to retrieve shop details.',
        [],
        500
    );
}


// ============================================================
// FINAL STATUS VALIDATION
// ============================================================

if (
    !in_array(
        $shop['status'],
        [
            'active',
            'inactive'
        ],
        true
    )
) {

    response(
        false,
        'Shop status could not be verified after update.',
        [],
        500
    );
}


// ============================================================
// UPDATED FIELDS RESPONSE
// ============================================================

$updated_fields = [];

foreach ($update_values as $field => $value) {

    $updated_fields[] =
        $field;
}


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Shop updated successfully.',
    [

        'updated_fields' =>
            $updated_fields,

        'shop' => [

            'id' =>
                (int) $shop['id'],

            'shop_name' =>
                $shop['shop_name'],

            'owner_name' =>
                $shop['owner_name'],

            'phone' =>
                $shop['phone'],

            'email' =>
                $shop['email'],

            'address' =>
                $shop['address'],

            'username' =>
                $shop['username'],

            'qr_code' =>
                $shop['qr_code'],

            'status' =>
                $shop['status'],

            'last_login_at' =>
                $shop['last_login_at'],

            'created_at' =>
                $shop['created_at'],

            'updated_at' =>
                $shop['updated_at']
        ]
    ],
    200
);