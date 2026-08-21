<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

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
// QR SLUG REQUIRED
// ============================================================

if (
    !isset($_GET['qr']) ||
    trim((string) $_GET['qr']) === ''
) {

    response(
        false,
        'QR slug is required.',
        [],
        422
    );
}


// ============================================================
// GET QR SLUG
// ============================================================

$qr_slug = trim(
    (string) $_GET['qr']
);


// ============================================================
// QR SLUG LENGTH VALIDATION
// ============================================================

if (
    strlen($qr_slug) < 10 ||
    strlen($qr_slug) > 100
) {

    response(
        false,
        'Invalid QR slug.',
        [],
        422
    );
}



if (
    !preg_match(
        '/^WE3X-QR-[A-Z0-9]+$/',
        $qr_slug
    )
) {

    response(
        false,
        'Invalid QR slug format.',
        [],
        422
    );
}


// ============================================================
// FIND SHOP
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            shop_name,
            qr_code,
            status
        FROM shops
        WHERE qr_code = ?
        LIMIT 1
    ");

    $stmt->execute([
        $qr_slug
    ]);

    $shop = $stmt->fetch();

} catch (PDOException $e) {

    response(
        false,
        'Failed to verify QR code.',
        [],
        500
    );
}


// ============================================================
// QR NOT FOUND
// ============================================================

if (!$shop) {

    response(
        false,
        'Invalid or unregistered QR code.',
        [],
        404
    );
}


// ============================================================
// DATABASE QR VALIDATION
// ============================================================

if (
    !isset($shop['qr_code']) ||
    trim((string) $shop['qr_code']) === ''
) {

    response(
        false,
        'QR code is not configured for this shop.',
        [],
        404
    );
}


// ============================================================
// DATABASE QR MATCH VALIDATION
// ============================================================

if (
    !hash_equals(
        (string) $shop['qr_code'],
        $qr_slug
    )
) {

    response(
        false,
        'Invalid QR code.',
        [],
        404
    );
}


// ============================================================
// SHOP STATUS VALIDATION
// ============================================================

if ($shop['status'] !== 'active') {

    response(
        false,
        'This QR code is currently inactive.',
        [],
        403
    );
}


try {

    $registration_token = bin2hex(
        random_bytes(32)
    );

} catch (Throwable $e) {

    response(
        false,
        'Failed to generate registration token.',
        [],
        500
    );
}


// ============================================================
// TOKEN FORMAT VALIDATION
// ============================================================

if (
    strlen($registration_token) !== 64 ||
    !preg_match(
        '/^[a-f0-9]{64}$/',
        $registration_token
    )
) {

    response(
        false,
        'Invalid registration token generated.',
        [],
        500
    );
}


// ============================================================
// TOKEN HASH
// ============================================================

$token_hash = hash(
    'sha256',
    $registration_token
);


// ============================================================
// TOKEN HASH VALIDATION
// ============================================================

if (
    strlen($token_hash) !== 64 ||
    !preg_match(
        '/^[a-f0-9]{64}$/',
        $token_hash
    )
) {

    response(
        false,
        'Failed to secure registration token.',
        [],
        500
    );
}


// ============================================================
// TOKEN EXPIRY
// ============================================================
//
// 60 minutes
// 60 × 60 = 3600 seconds
//

$expires_at = date(
    'Y-m-d H:i:s',
    time() + 3600
);


// ============================================================
// EXPIRY VALIDATION
// ============================================================

if (
    strtotime($expires_at) === false ||
    strtotime($expires_at) <= time()
) {

    response(
        false,
        'Failed to generate token expiry time.',
        [],
        500
    );
}


// ============================================================
// START TRANSACTION
// ============================================================

try {

    $pdo->beginTransaction();


    // ========================================================
    // VERIFY SHOP AGAIN WITH LOCK
    // ========================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            shop_name,
            qr_code,
            status
        FROM shops
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $shop['id']
    ]);

    $locked_shop = $stmt->fetch();


    // ========================================================
    // SHOP NOT FOUND AFTER LOCK
    // ========================================================

    if (!$locked_shop) {

        $pdo->rollBack();

        response(
            false,
            'Shop associated with this QR code was not found.',
            [],
            404
        );
    }


    // ========================================================
    // SHOP STATUS RECHECK
    // ========================================================

    if ($locked_shop['status'] !== 'active') {

        $pdo->rollBack();

        response(
            false,
            'This QR code is currently inactive.',
            [],
            403
        );
    }



    $stmt = $pdo->prepare("
        INSERT INTO student_registration_tokens (
            shop_id,
            token_hash,
            expires_at
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $locked_shop['id'],
        $token_hash,
        $expires_at
    ]);


    // ========================================================
    // TOKEN ID
    // ========================================================

    $token_id = (int) $pdo->lastInsertId();


    // ========================================================
    // TOKEN INSERT VALIDATION
    // ========================================================

    if ($token_id <= 0) {

        $pdo->rollBack();

        response(
            false,
            'Registration token could not be created.',
            [],
            500
        );
    }


    // ========================================================
    // COMMIT
    // ========================================================

    $pdo->commit();


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    // ========================================================
    // DUPLICATE TOKEN PROTECTION
    // ========================================================

    if (
        $e->getCode() === '23000'
    ) {

        response(
            false,
            'Registration token collision occurred. Please scan the QR code again.',
            [],
            409
        );
    }


    response(
        false,
        'Registration token could not be created.',
        [],
        500
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }

    response(
        false,
        'Failed to process QR verification.',
        [],
        500
    );
}




response(
    true,
    'QR code verified successfully. Registration token generated.',
    [
        'shop' => [
            'name' => $locked_shop['shop_name']
        ],

        'registration' => [
            'token'      => $registration_token,
            'expires_at' => $expires_at,
            'valid_for'  => 3600
        ]
    ],
    200
);