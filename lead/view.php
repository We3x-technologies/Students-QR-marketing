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
// BEARER TOKEN FORMAT
// ============================================================

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
// TOKEN VALIDATION
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
// LEAD ID REQUIRED
// ============================================================

if (
    !isset($_GET['lead_id']) ||
    trim((string) $_GET['lead_id']) === ''
) {

    response(
        false,
        'Lead ID is required.',
        [],
        422
    );
}


// ============================================================
// LEAD ID VALIDATION
// ============================================================

$lead_id_input = trim(
    (string) $_GET['lead_id']
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
// GET LEAD + SHOP + STUDENT
// ============================================================

$sql = "
    SELECT

        -- ====================================================
        -- LEAD DETAILS
        -- ====================================================

        sl.id AS lead_id,
        sl.status AS lead_status,
        sl.admin_remarks,
        sl.contacted_at,
        sl.converted_at,
        sl.rejected_at,
        sl.created_at AS lead_created_at,
        sl.updated_at AS lead_updated_at,

        -- ====================================================
        -- SHOP DETAILS
        -- ====================================================

        sh.id AS shop_id,
        sh.shop_name,
        sh.owner_name,
        sh.phone AS shop_phone,
        sh.email AS shop_email,
        sh.address AS shop_address,
        sh.username AS shop_username,
        sh.status AS shop_status,

        -- ====================================================
        -- STUDENT DETAILS
        -- ====================================================

        s.id AS student_id,
        s.name AS student_name,
        s.gender,
        s.phone AS student_phone,
        s.email AS student_email,
        s.current_location,
        s.highest_qualification,
        s.institution_name,
        s.current_status,
        s.current_status_other,
        s.course_applied,
        s.training_mode,
        s.declaration,
        s.created_at AS student_created_at,
        s.updated_at AS student_updated_at

    FROM student_leads sl

    INNER JOIN students s
        ON s.id = sl.student_id

    INNER JOIN shops sh
        ON sh.id = sl.shop_id

    WHERE sl.id = ?

    LIMIT 1
";


try {

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $lead_id
    ]);

    $lead = $stmt->fetch();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve lead details.',
        [],
        500
    );
}


// ============================================================
// LEAD NOT FOUND
// ============================================================

if (!$lead) {

    response(
        false,
        'Lead not found.',
        [],
        404
    );
}


// ============================================================
// VALIDATE LEAD STATUS
// ============================================================

$allowed_statuses = [
    'need_to_contact',
    'converted',
    'rejected'
];

if (
    !in_array(
        $lead['lead_status'],
        $allowed_statuses,
        true
    )
) {

    response(
        false,
        'Invalid lead status stored in database.',
        [],
        500
    );
}


// ============================================================
// GET STATUS HISTORY
// ============================================================

$history_sql = "
    SELECT

        -- ====================================================
        -- HISTORY
        -- ====================================================

        lsh.id,
        lsh.old_status,
        lsh.new_status,
        lsh.remarks,
        lsh.created_at,

        -- ====================================================
        -- ADMIN
        -- ====================================================

        a.id AS admin_id,
        a.name AS admin_name,
        a.username AS admin_username

    FROM lead_status_history lsh

    INNER JOIN admins a
        ON a.id = lsh.changed_by_admin

    WHERE lsh.lead_id = ?

    ORDER BY lsh.created_at DESC, lsh.id DESC
";


try {

    $stmt = $pdo->prepare($history_sql);

    $stmt->execute([
        $lead_id
    ]);

    $history_rows = $stmt->fetchAll();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve lead status history.',
        [],
        500
    );
}


// ============================================================
// FORMAT STATUS HISTORY
// ============================================================

$status_history = [];

foreach ($history_rows as $history) {

    $status_history[] = [

        'id' => (int) $history['id'],

        'old_status' => $history['old_status'],

        'new_status' => $history['new_status'],

        'remarks' => $history['remarks'],

        'changed_by' => [
            'admin_id'       => (int) $history['admin_id'],
            'admin_name'     => $history['admin_name'],
            'admin_username' => $history['admin_username']
        ],

        'created_at' => $history['created_at']
    ];
}


// ============================================================
// FORMAT RESPONSE
// ============================================================

$response_data = [

    // ========================================================
    // LEAD
    // ========================================================

    'lead' => [

        'id' => (int) $lead['lead_id'],

        'status' => $lead['lead_status'],

        'admin_remarks' => $lead['admin_remarks'],

        'contacted_at' => $lead['contacted_at'],

        'converted_at' => $lead['converted_at'],

        'rejected_at' => $lead['rejected_at'],

        'created_at' => $lead['lead_created_at'],

        'updated_at' => $lead['lead_updated_at']
    ],


    // ========================================================
    // SHOP
    // ========================================================

    'shop' => [

        'id' => (int) $lead['shop_id'],

        'shop_name' => $lead['shop_name'],

        'owner_name' => $lead['owner_name'],

        'phone' => $lead['shop_phone'],

        'email' => $lead['shop_email'],

        'address' => $lead['shop_address'],

        'username' => $lead['shop_username'],

        'status' => $lead['shop_status']
    ],


    // ========================================================
    // STUDENT
    // ========================================================

    'student' => [

        'id' => (int) $lead['student_id'],

        'name' => $lead['student_name'],

        'gender' => $lead['gender'],

        'phone' => $lead['student_phone'],

        'email' => $lead['student_email'],

        'current_location' => $lead['current_location'],

        'highest_qualification' =>
            $lead['highest_qualification'],

        'institution_name' =>
            $lead['institution_name'],

        'current_status' =>
            $lead['current_status'],

        'current_status_other' =>
            $lead['current_status_other'],

        'course_applied' =>
            $lead['course_applied'],

        'training_mode' =>
            $lead['training_mode'],

        'declaration' =>
            (bool) $lead['declaration'],

        'created_at' =>
            $lead['student_created_at'],

        'updated_at' =>
            $lead['student_updated_at']
    ],


    // ========================================================
    // STATUS HISTORY
    // ========================================================

    'status_history' => $status_history
];


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Lead details retrieved successfully.',
    $response_data,
    200
);