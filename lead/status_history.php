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
// PAGINATION
// ============================================================

$page = 1;

if (
    isset($_GET['page']) &&
    $_GET['page'] !== ''
) {

    if (
        !preg_match(
            '/^[0-9]+$/',
            (string) $_GET['page']
        )
    ) {

        response(
            false,
            'Invalid page number.',
            [],
            422
        );
    }

    $page = (int) $_GET['page'];
}


if ($page < 1) {

    response(
        false,
        'Page must be greater than or equal to 1.',
        [],
        422
    );
}


// ============================================================
// LIMIT
// ============================================================

$limit = 20;

if (
    isset($_GET['limit']) &&
    $_GET['limit'] !== ''
) {

    if (
        !preg_match(
            '/^[0-9]+$/',
            (string) $_GET['limit']
        )
    ) {

        response(
            false,
            'Invalid limit.',
            [],
            422
        );
    }

    $limit = (int) $_GET['limit'];
}


if ($limit < 1 || $limit > 100) {

    response(
        false,
        'Limit must be between 1 and 100.',
        [],
        422
    );
}


$offset = ($page - 1) * $limit;


// ============================================================
// FROM DATE
// ============================================================

$from_date = '';

if (isset($_GET['from_date'])) {

    $from_date = trim(
        (string) $_GET['from_date']
    );

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $from_date
        )
    ) {

        response(
            false,
            'Invalid from_date format. Use YYYY-MM-DD.',
            [],
            422
        );
    }

    $date_object = DateTime::createFromFormat(
        'Y-m-d',
        $from_date
    );

    if (
        !$date_object ||
        $date_object->format('Y-m-d') !== $from_date
    ) {

        response(
            false,
            'Invalid from_date.',
            [],
            422
        );
    }
}


// ============================================================
// TO DATE
// ============================================================

$to_date = '';

if (isset($_GET['to_date'])) {

    $to_date = trim(
        (string) $_GET['to_date']
    );

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $to_date
        )
    ) {

        response(
            false,
            'Invalid to_date format. Use YYYY-MM-DD.',
            [],
            422
        );
    }

    $date_object = DateTime::createFromFormat(
        'Y-m-d',
        $to_date
    );

    if (
        !$date_object ||
        $date_object->format('Y-m-d') !== $to_date
    ) {

        response(
            false,
            'Invalid to_date.',
            [],
            422
        );
    }
}


// ============================================================
// DATE RANGE VALIDATION
// ============================================================

if (
    $from_date !== '' &&
    $to_date !== ''
) {

    if ($from_date > $to_date) {

        response(
            false,
            'from_date cannot be greater than to_date.',
            [],
            422
        );
    }
}


// ============================================================
// VERIFY LEAD EXISTS
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            sl.id,
            sl.shop_id,
            sl.student_id,
            sl.status,
            sl.created_at,
            sl.updated_at,

            sh.shop_name,

            s.name AS student_name,
            s.phone AS student_phone

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
// STATUS HISTORY COUNT
// ============================================================

$count_sql = "
    SELECT COUNT(*)
    FROM lead_status_history
    WHERE lead_id = ?
";

$count_params = [
    $lead_id
];


// ============================================================
// COUNT DATE FILTER
// ============================================================

if ($from_date !== '') {

    $count_sql .= "
        AND created_at >= ?
    ";

    $count_params[] =
        $from_date . ' 00:00:00';
}


if ($to_date !== '') {

    $count_sql .= "
        AND created_at <= ?
    ";

    $count_params[] =
        $to_date . ' 23:59:59';
}


try {

    $stmt = $pdo->prepare($count_sql);

    $stmt->execute(
        $count_params
    );

    $total_records =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve status history count.',
        [],
        500
    );
}


// ============================================================
// TOTAL PAGES
// ============================================================

$total_pages = $total_records > 0
    ? (int) ceil(
        $total_records / $limit
    )
    : 0;


// ============================================================
// PAGE VALIDATION
// ============================================================

if (
    $total_records > 0 &&
    $page > $total_pages
) {

    response(
        false,
        'Requested page does not exist.',
        [],
        404
    );
}


// ============================================================
// STATUS HISTORY QUERY
// ============================================================

$sql = "
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
";


$params = [
    $lead_id
];


// ============================================================
// FROM DATE
// ============================================================

if ($from_date !== '') {

    $sql .= "
        AND lsh.created_at >= ?
    ";

    $params[] =
        $from_date . ' 00:00:00';
}


// ============================================================
// TO DATE
// ============================================================

if ($to_date !== '') {

    $sql .= "
        AND lsh.created_at <= ?
    ";

    $params[] =
        $to_date . ' 23:59:59';
}


// ============================================================
// ORDER + PAGINATION
// ============================================================

$sql .= "
    ORDER BY
        lsh.created_at DESC,
        lsh.id DESC

    LIMIT $limit
    OFFSET $offset
";


try {

    $stmt = $pdo->prepare($sql);

    $stmt->execute(
        $params
    );

    $rows = $stmt->fetchAll();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve status history.',
        [],
        500
    );
}


// ============================================================
// FORMAT STATUS HISTORY
// ============================================================

$status_history = [];

$allowed_statuses = [
    'need_to_contact',
    'converted',
    'rejected'
];


foreach ($rows as $row) {

    // ========================================================
    // DATABASE STATUS VALIDATION
    // ========================================================

    if (
        $row['old_status'] !== null &&
        !in_array(
            $row['old_status'],
            $allowed_statuses,
            true
        )
    ) {

        response(
            false,
            'Invalid old status found in status history.',
            [],
            500
        );
    }


    if (
        !in_array(
            $row['new_status'],
            $allowed_statuses,
            true
        )
    ) {

        response(
            false,
            'Invalid new status found in status history.',
            [],
            500
        );
    }


    // ========================================================
    // HISTORY DATA
    // ========================================================

    $status_history[] = [

        'id' =>
            (int) $row['id'],

        'old_status' =>
            $row['old_status'],

        'new_status' =>
            $row['new_status'],

        'remarks' =>
            $row['remarks'],

        'changed_by' => [

            'admin_id' =>
                (int) $row['admin_id'],

            'admin_name' =>
                $row['admin_name'],

            'admin_username' =>
                $row['admin_username']
        ],

        'created_at' =>
            $row['created_at']
    ];
}


// ============================================================
// PAGINATION
// ============================================================

$pagination = [

    'current_page' =>
        $page,

    'per_page' =>
        $limit,

    'total_records' =>
        $total_records,

    'total_pages' =>
        $total_pages,

    'has_previous' =>
        $page > 1,

    'has_next' =>
        (
            $total_pages > 0 &&
            $page < $total_pages
        )
];


// ============================================================
// FILTERS
// ============================================================

$filters = [

    'from_date' =>
        $from_date !== ''
            ? $from_date
            : null,

    'to_date' =>
        $to_date !== ''
            ? $to_date
            : null
];


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Lead status history retrieved successfully.',
    [

        // ====================================================
        // LEAD BASIC DETAILS
        // ====================================================

        'lead' => [

            'id' =>
                (int) $lead['id'],

            'shop_id' =>
                (int) $lead['shop_id'],

            'shop_name' =>
                $lead['shop_name'],

            'student_id' =>
                (int) $lead['student_id'],

            'student_name' =>
                $lead['student_name'],

            'student_phone' =>
                $lead['student_phone'],

            'current_status' =>
                $lead['status'],

            'created_at' =>
                $lead['created_at'],

            'updated_at' =>
                $lead['updated_at']
        ],

        // ====================================================
        // STATUS HISTORY
        // ====================================================

        'status_history' =>
            $status_history,

        // ====================================================
        // PAGINATION
        // ====================================================

        'pagination' =>
            $pagination,

        // ====================================================
        // FILTERS
        // ====================================================

        'filters' =>
            $filters
    ],
    200
);