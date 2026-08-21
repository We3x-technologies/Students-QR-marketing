<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;


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
// CHECK SHOP
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
        status
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
// SHOP STATUS CHECK
// ============================================================

if ($shop['status'] !== 'active') {

    response(
        false,
        'Cannot generate QR for an inactive shop.',
        [],
        403
    );
}


// ============================================================
// GENERATE UNIQUE QR SLUG
// ============================================================

try {

    $qr_slug = 'WE3X-QR-' . strtoupper(
        bin2hex(random_bytes(8))
    );

} catch (Throwable $e) {

    response(
        false,
        'Failed to generate secure QR slug.',
        [],
        500
    );
}


// ============================================================
// DUPLICATE QR SLUG CHECK
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        id
    FROM shops
    WHERE qr_code = ?
    LIMIT 1
");

$stmt->execute([
    $qr_slug
]);

if ($stmt->fetch()) {

    response(
        false,
        'QR slug already exists. Please generate again.',
        [],
        409
    );
}


// ============================================================
// SHOP QR DIRECTORY
// ============================================================

$shop_qr_folder = QR_FOLDER . $shop_id . '/';


// ============================================================
// CREATE SHOP QR DIRECTORY
// ============================================================

if (!is_dir($shop_qr_folder)) {

    if (!mkdir($shop_qr_folder, 0755, true)) {

        response(
            false,
            'Shop QR directory could not be created.',
            [],
            500
        );
    }
}


// ============================================================
// DIRECTORY WRITABLE CHECK
// ============================================================

if (!is_writable($shop_qr_folder)) {

    response(
        false,
        'Shop QR directory is not writable.',
        [],
        500
    );
}


// ============================================================
// QR FILE NAME
// ============================================================

$file_name = 'we3x_qr_' . $qr_slug . '.png';

$file_path = $shop_qr_folder . $file_name;


// ============================================================
// QR CONTENT
// ============================================================
//
// QR image-க்குள் URL இல்லை.
// Slug மட்டும் இருக்கும்.
//
// Example:
//
// WE3X-QR-A81F92C7D8E1
//

$qr_content = $qr_slug;


// ============================================================
// QR OPTIONS
// ============================================================

$options = new QROptions();

$options->outputInterface = QRGdImagePNG::class;

$options->outputBase64 = false;

$options->eccLevel = 'M';

$options->scale = 10;


// ============================================================
// GENERATE QR IMAGE
// ============================================================

try {

    (new QRCode($options))->render(
        $qr_content,
        $file_path
    );

} catch (Throwable $e) {

    response(
        false,
        'QR image generation failed.',
        [
            'error' => $e->getMessage()
        ],
        500
    );
}


// ============================================================
// QR FILE VALIDATION
// ============================================================

if (!file_exists($file_path)) {

    response(
        false,
        'QR image was not created.',
        [],
        500
    );
}


if (filesize($file_path) <= 0) {

    @unlink($file_path);

    response(
        false,
        'Generated QR image is empty.',
        [],
        500
    );
}


// ============================================================
// VALIDATE PNG FILE
// ============================================================

$image_info = @getimagesize($file_path);

if ($image_info === false) {

    @unlink($file_path);

    response(
        false,
        'Generated QR image is invalid.',
        [],
        500
    );
}



$old_files = glob($shop_qr_folder . '*.png');

if ($old_files !== false) {

    foreach ($old_files as $old_file) {

        if (
            is_file($old_file) &&
            $old_file !== $file_path
        ) {

            @unlink($old_file);
        }
    }
}


// ============================================================
// DATABASE UPDATE
// ============================================================

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE shops
        SET qr_code = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $qr_slug,
        $shop_id
    ]);


    if ($stmt->rowCount() !== 1) {

        $pdo->rollBack();

        @unlink($file_path);

        response(
            false,
            'QR code could not be saved.',
            [],
            500
        );
    }


    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }

    @unlink($file_path);

    response(
        false,
        'QR code could not be saved.',
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

$updated_shop = $stmt->fetch();


// ============================================================
// UPDATED SHOP VALIDATION
// ============================================================

if (!$updated_shop) {

    response(
        false,
        'QR generated but shop details could not be retrieved.',
        [],
        500
    );
}


// ============================================================
// QR RELATIVE PATH
// ============================================================

$qr_relative_path =
    'uploads/qr/' .
    $shop_id .
    '/' .
    $file_name;




$qr_url =
    rtrim(BASE_URL, '/') .
    '/' .
    ltrim($qr_relative_path, '/');


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'QR code generated successfully.',
    [
        'shop' => [
            'id'            => (int) $updated_shop['id'],
            'shop_name'     => $updated_shop['shop_name'],
            'owner_name'    => $updated_shop['owner_name'],
            'phone'         => $updated_shop['phone'],
            'email'         => $updated_shop['email'],
            'address'       => $updated_shop['address'],
            'username'      => $updated_shop['username'],
            'status'        => $updated_shop['status'],
            'last_login_at' => $updated_shop['last_login_at'],
            'created_at'    => $updated_shop['created_at'],
            'updated_at'    => $updated_shop['updated_at']
        ],

        'qr' => [
            'slug'   => $updated_shop['qr_code'],
            'file'   => $file_name,
            'path'   => $qr_relative_path,
            'qr_url' => $qr_url
        ]
    ],
    200
);