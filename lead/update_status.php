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
// TOKEN REQUIRED
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
        'Invalid authorization format.',
        [],
        401
    );
}


$token = trim($matches[1]);


// ============================================================
// EMPTY TOKEN CHECK
// ============================================================

if ($token === '') {

    response(
        false,
        'Authorization token is required.',
        [],
        401
    );
}


// ============================================================
// VERIFY JWT TOKEN
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
// GET ADMIN ID FROM TOKEN
// ============================================================
//
// Supports common JWT payload keys.
//

$admin_id = null;

if (isset($payload['user_id'])) {

    $admin_id = $payload['user_id'];

} elseif (isset($payload['id'])) {

    $admin_id = $payload['id'];

} elseif (isset($payload['sub'])) {

    $admin_id = $payload['sub'];
}


// ============================================================
// ADMIN ID VALIDATION
// ============================================================

if (
    $admin_id === null ||
    !preg_match('/^[0-9]+$/', (string) $admin_id)
) {

    response(
        false,
        'Invalid admin information in token.',
        [],
        401
    );
}

$admin_id = (int) $admin_id;


if ($admin_id < 1) {

    response(
        false,
        'Invalid admin information in token.',
        [],
        401
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
// LEAD ID REQUIRED
// ============================================================

if (
    !array_key_exists('lead_id', $input) ||
    $input['lead_id'] === null ||
    trim((string) $input['lead_id']) === ''
) {

    response(
        false,
        'Lead ID is required.',
        [],
        422
    );
}


// ============================================================
// STATUS REQUIRED
// ============================================================

if (
    !array_key_exists('status', $input) ||
    $input['status'] === null ||
    trim((string) $input['status']) === ''
) {

    response(
        false,
        'Status is required.',
        [],
        422
    );
}


// ============================================================
// LEAD ID VALIDATION
// ============================================================

$lead_id_input = trim(
    (string) $input['lead_id']
);

if (!preg_match('/^[0-9]+$/', $lead_id_input)) {

    response(
        false,
        'Invalid Lead ID.',
        [],
        422
    );
}

$lead_id = (int) $lead_id_input;


if ($lead_id < 1) {

    response(
        false,
        'Invalid Lead ID.',
        [],
        422
    );
}


// ============================================================
// STATUS INPUT
// ============================================================

$new_status = strtolower(
    trim((string) $input['status'])
);


// ============================================================
// STATUS VALIDATION
// ============================================================

$allowed_statuses = [
    'need_to_contact',
    'converted',
    'rejected'
];


if (!in_array($new_status, $allowed_statuses, true)) {

    response(
        false,
        'Invalid status. Allowed values: need_to_contact, converted, rejected.',
        [],
        422
    );
}


// ============================================================
// REMARKS
// ============================================================
//
// Remarks are optional.
//

$remarks = null;

if (array_key_exists('remarks', $input)) {

    if ($input['remarks'] === null) {

        $remarks = null;

    } else {

        $remarks = trim(
            (string) $input['remarks']
        );

        if (strlen($remarks) > 2000) {

            response(
                false,
                'Remarks cannot exceed 2000 characters.',
                [],
                422
            );
        }

        if ($remarks === '') {

            $remarks = null;
        }
    }
}


// ============================================================
// VERIFY ADMIN ACCOUNT
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            username,
            status
        FROM admins
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $admin_id
    ]);

    $admin = $stmt->fetch();

} catch (PDOException $e) {

    response(
        false,
        'Failed to validate admin account.',
        [],
        500
    );
}


// ============================================================
// ADMIN NOT FOUND
// ============================================================

if (!$admin) {

    response(
        false,
        'Admin account not found.',
        [],
        401
    );
}


// ============================================================
// ADMIN STATUS CHECK
// ============================================================

if ($admin['status'] !== 'active') {

    response(
        false,
        'Admin account is inactive.',
        [],
        403
    );
}


// ============================================================
// START TRANSACTION
// ============================================================

try {

    $pdo->beginTransaction();


    // ========================================================
    // GET LEAD WITH ROW LOCK
    // ========================================================

    $stmt = $pdo->prepare("
        SELECT
            sl.id,
            sl.shop_id,
            sl.student_id,
            sl.status,
            sl.admin_remarks,
            sl.contacted_at,
            sl.converted_at,
            sl.rejected_at,
            sl.created_at,
            sl.updated_at,

            sh.shop_name,
            sh.status AS shop_status,

            s.name AS student_name,
            s.phone AS student_phone,
            s.email AS student_email,
            s.course_applied

        FROM student_leads sl

        INNER JOIN shops sh
            ON sh.id = sl.shop_id

        INNER JOIN students s
            ON s.id = sl.student_id

        WHERE sl.id = ?

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        $lead_id
    ]);

    $lead = $stmt->fetch();


    // ========================================================
    // LEAD NOT FOUND
    // ========================================================

    if (!$lead) {

        $pdo->rollBack();

        response(
            false,
            'Lead not found.',
            [],
            404
        );
    }


    // ========================================================
    // VALIDATE CURRENT DATABASE STATUS
    // ========================================================

    if (
        !in_array(
            $lead['status'],
            $allowed_statuses,
            true
        )
    ) {

        $pdo->rollBack();

        response(
            false,
            'Invalid lead status stored in database.',
            [],
            500
        );
    }


    // ========================================================
    // SAME STATUS CHECK
    // ========================================================

    if ($lead['status'] === $new_status) {

        $pdo->rollBack();

        response(
            false,
            'Lead is already in this status.',
            [
                'lead_id' => (int) $lead['id'],
                'status'  => $lead['status']
            ],
            409
        );
    }


    // ========================================================
    // SHOP STATUS CHECK
    // ========================================================

    if ($lead['shop_status'] !== 'active') {

        $pdo->rollBack();

        response(
            false,
            'The shop associated with this lead is inactive.',
            [],
            403
        );
    }


    // ========================================================
    // OLD STATUS
    // ========================================================

    $old_status = $lead['status'];


    // ========================================================
    // TIMESTAMP VALUES
    // ========================================================

    $contacted_at = $lead['contacted_at'];
    $converted_at = null;
    $rejected_at  = null;


    // ========================================================
    // STATUS TIMESTAMP LOGIC
    // ========================================================

    if ($new_status === 'need_to_contact') {

        // Lead has not been converted or rejected.

        $converted_at = null;

        $rejected_at = null;

    } elseif ($new_status === 'converted') {

        // Once admin handles the lead,
        // contacted_at must be recorded.

        if ($contacted_at === null) {

            $contacted_at = date(
                'Y-m-d H:i:s'
            );
        }

        $converted_at = date(
            'Y-m-d H:i:s'
        );

        $rejected_at = null;

    } elseif ($new_status === 'rejected') {

        // Once admin handles the lead,
        // contacted_at must be recorded.

        if ($contacted_at === null) {

            $contacted_at = date(
                'Y-m-d H:i:s'
            );
        }

        $converted_at = null;

        $rejected_at = date(
            'Y-m-d H:i:s'
        );
    }


    // ========================================================
    // UPDATE LEAD
    // ========================================================

    $stmt = $pdo->prepare("
        UPDATE student_leads
        SET
            status = ?,
            admin_remarks = ?,
            contacted_at = ?,
            converted_at = ?,
            rejected_at = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $new_status,
        $remarks,
        $contacted_at,
        $converted_at,
        $rejected_at,
        $lead_id
    ]);


    // ========================================================
    // UPDATE VALIDATION
    // ========================================================

    if ($stmt->rowCount() !== 1) {

        $pdo->rollBack();

        response(
            false,
            'Lead status could not be updated.',
            [],
            500
        );
    }


    // ========================================================
    // INSERT STATUS HISTORY
    // ========================================================

    $stmt = $pdo->prepare("
        INSERT INTO lead_status_history (
            lead_id,
            old_status,
            new_status,
            remarks,
            changed_by_admin
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $lead_id,
        $old_status,
        $new_status,
        $remarks,
        $admin_id
    ]);


    // ========================================================
    // HISTORY INSERT VALIDATION
    // ========================================================

    $history_id = (int) $pdo->lastInsertId();

    if ($history_id <= 0) {

        $pdo->rollBack();

        response(
            false,
            'Lead status history could not be created.',
            [],
            500
        );
    }


    // ========================================================
    // COMMIT TRANSACTION
    // ========================================================

    $pdo->commit();


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }

    response(
        false,
        'Failed to update lead status due to a database error.',
        [],
        500
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }

    response(
        false,
        'Failed to update lead status.',
        [],
        500
    );
}


// ============================================================
// FETCH UPDATED LEAD
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT

            sl.id,
            sl.shop_id,
            sl.student_id,
            sl.status,
            sl.admin_remarks,
            sl.contacted_at,
            sl.converted_at,
            sl.rejected_at,
            sl.created_at,
            sl.updated_at,

            sh.shop_name,

            s.name AS student_name,
            s.phone AS student_phone,
            s.email AS student_email,
            s.course_applied

        FROM student_leads sl

        INNER JOIN shops sh
            ON sh.id = sl.shop_id

        INNER JOIN students s
            ON s.id = sl.student_id

        WHERE sl.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $lead_id
    ]);

    $updated_lead = $stmt->fetch();

} catch (PDOException $e) {

    response(
        false,
        'Lead updated, but failed to retrieve updated details.',
        [],
        500
    );
}


// ============================================================
// FINAL VALIDATION
// ============================================================

if (!$updated_lead) {

    response(
        false,
        'Lead was updated but updated details could not be retrieved.',
        [],
        500
    );
}


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Lead status updated successfully.',
    [
        'lead' => [
            'id' => (int) $updated_lead['id'],

            'shop_id' => (int) $updated_lead['shop_id'],

            'shop_name' => $updated_lead['shop_name'],

            'student_id' => (int) $updated_lead['student_id'],

            'student_name' => $updated_lead['student_name'],

            'student_phone' => $updated_lead['student_phone'],

            'student_email' => $updated_lead['student_email'],

            'course_applied' => $updated_lead['course_applied'],

            'old_status' => $old_status,

            'status' => $updated_lead['status'],

            'admin_remarks' =>
                $updated_lead['admin_remarks'],

            'contacted_at' =>
                $updated_lead['contacted_at'],

            'converted_at' =>
                $updated_lead['converted_at'],

            'rejected_at' =>
                $updated_lead['rejected_at'],

            'created_at' =>
                $updated_lead['created_at'],

            'updated_at' =>
                $updated_lead['updated_at']
        ],

        'status_history' => [
            'id' => $history_id,

            'old_status' => $old_status,

            'new_status' => $new_status,

            'remarks' => $remarks,

            'changed_by_admin' => [
                'id' => (int) $admin['id'],
                'name' => $admin['name'],
                'username' => $admin['username']
            ]
        ]
    ],
    200
);