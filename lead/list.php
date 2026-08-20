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
// PAGINATION
// ============================================================

$page = 1;

if (isset($_GET['page']) && $_GET['page'] !== '') {

    if (
        !preg_match('/^[0-9]+$/', (string) $_GET['page'])
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

if (isset($_GET['limit']) && $_GET['limit'] !== '') {

    if (
        !preg_match('/^[0-9]+$/', (string) $_GET['limit'])
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
// SEARCH
// ============================================================
//
// Search fields:
// student name
// phone
// email
// shop name
//

$search = '';

if (isset($_GET['search'])) {

    $search = trim(
        (string) $_GET['search']
    );

    if (strlen($search) > 100) {

        response(
            false,
            'Search value cannot exceed 100 characters.',
            [],
            422
        );
    }
}


// ============================================================
// STATUS FILTER
// ============================================================

$status = '';

if (isset($_GET['status'])) {

    $status = strtolower(
        trim((string) $_GET['status'])
    );

    $allowed_statuses = [
        'need_to_contact',
        'converted',
        'rejected'
    ];

    if (!in_array($status, $allowed_statuses, true)) {

        response(
            false,
            'Invalid lead status. Allowed values: need_to_contact, converted, rejected.',
            [],
            422
        );
    }
}


// ============================================================
// SHOP ID FILTER
// ============================================================

$shop_id = null;

if (
    isset($_GET['shop_id']) &&
    $_GET['shop_id'] !== ''
) {

    if (
        !preg_match('/^[0-9]+$/', (string) $_GET['shop_id'])
    ) {

        response(
            false,
            'Invalid shop ID.',
            [],
            422
        );
    }

    $shop_id = (int) $_GET['shop_id'];

    if ($shop_id < 1) {

        response(
            false,
            'Invalid shop ID.',
            [],
            422
        );
    }
}


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
// BUILD WHERE CONDITIONS
// ============================================================

$where = [];

$params = [];


// ============================================================
// SEARCH CONDITION
// ============================================================

if ($search !== '') {

    $where[] = "(
        s.name LIKE ?
        OR s.phone LIKE ?
        OR s.email LIKE ?
        OR sh.shop_name LIKE ?
    )";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
}


// ============================================================
// STATUS CONDITION
// ============================================================

if ($status !== '') {

    $where[] = "sl.status = ?";

    $params[] = $status;
}


// ============================================================
// SHOP CONDITION
// ============================================================

if ($shop_id !== null) {

    $where[] = "sl.shop_id = ?";

    $params[] = $shop_id;
}


// ============================================================
// FROM DATE CONDITION
// ============================================================

if ($from_date !== '') {

    $where[] = "sl.created_at >= ?";

    $params[] = $from_date . ' 00:00:00';
}


// ============================================================
// TO DATE CONDITION
// ============================================================

if ($to_date !== '') {

    $where[] = "sl.created_at <= ?";

    $params[] = $to_date . ' 23:59:59';
}


// ============================================================
// WHERE SQL
// ============================================================

$where_sql = '';

if (!empty($where)) {

    $where_sql = 'WHERE ' . implode(
        ' AND ',
        $where
    );
}


// ============================================================
// TOTAL COUNT
// ============================================================

$count_sql = "
    SELECT COUNT(*)
    FROM student_leads sl

    INNER JOIN students s
        ON s.id = sl.student_id

    INNER JOIN shops sh
        ON sh.id = sl.shop_id

    $where_sql
";


try {

    $stmt = $pdo->prepare($count_sql);

    $stmt->execute($params);

    $total_records = (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve lead count.',
        [],
        500
    );
}


// ============================================================
// TOTAL PAGES
// ============================================================

$total_pages = $total_records > 0
    ? (int) ceil($total_records / $limit)
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
// LEAD LIST QUERY
// ============================================================

$sql = "
    SELECT

        -- LEAD DETAILS
        sl.id AS lead_id,
        sl.status AS lead_status,
        sl.admin_remarks,
        sl.contacted_at,
        sl.converted_at,
        sl.rejected_at,
        sl.created_at AS lead_created_at,
        sl.updated_at AS lead_updated_at,

        -- SHOP DETAILS
        sh.id AS shop_id,
        sh.shop_name,
        sh.owner_name,
        sh.phone AS shop_phone,
        sh.email AS shop_email,
        sh.address AS shop_address,
        sh.username AS shop_username,
        sh.status AS shop_status,

        -- STUDENT DETAILS
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

    $where_sql

    ORDER BY sl.created_at DESC

    LIMIT $limit
    OFFSET $offset
";


try {

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rows = $stmt->fetchAll();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve leads.',
        [],
        500
    );
}


// ============================================================
// FORMAT LEADS
// ============================================================

$leads = [];

foreach ($rows as $row) {

    $leads[] = [

        // ====================================================
        // LEAD
        // ====================================================

        'lead' => [
            'id'            => (int) $row['lead_id'],
            'status'        => $row['lead_status'],
            'admin_remarks' => $row['admin_remarks'],
            'contacted_at'  => $row['contacted_at'],
            'converted_at'  => $row['converted_at'],
            'rejected_at'   => $row['rejected_at'],
            'created_at'    => $row['lead_created_at'],
            'updated_at'    => $row['lead_updated_at']
        ],


        // ====================================================
        // SHOP
        // ====================================================

        'shop' => [
            'id'         => (int) $row['shop_id'],
            'shop_name'  => $row['shop_name'],
            'owner_name' => $row['owner_name'],
            'phone'      => $row['shop_phone'],
            'email'      => $row['shop_email'],
            'address'    => $row['shop_address'],
            'username'   => $row['shop_username'],
            'status'     => $row['shop_status']
        ],


        // ====================================================
        // STUDENT
        // ====================================================

        'student' => [
            'id'                    => (int) $row['student_id'],
            'name'                  => $row['student_name'],
            'gender'                => $row['gender'],
            'phone'                 => $row['student_phone'],
            'email'                 => $row['student_email'],
            'current_location'      => $row['current_location'],
            'highest_qualification' => $row['highest_qualification'],
            'institution_name'      => $row['institution_name'],
            'current_status'        => $row['current_status'],
            'current_status_other'  => $row['current_status_other'],
            'course_applied'        => $row['course_applied'],
            'training_mode'         => $row['training_mode'],
            'declaration'           => (bool) $row['declaration'],
            'created_at'            => $row['student_created_at'],
            'updated_at'            => $row['student_updated_at']
        ]
    ];
}


// ============================================================
// PAGINATION DATA
// ============================================================

$pagination = [
    'current_page' => $page,
    'per_page'     => $limit,
    'total_records'=> $total_records,
    'total_pages'  => $total_pages,

    'has_previous' => $page > 1,

    'has_next' => (
        $total_pages > 0 &&
        $page < $total_pages
    )
];


// ============================================================
// FILTER DATA
// ============================================================

$filters = [
    'search'    => $search !== '' ? $search : null,
    'status'    => $status !== '' ? $status : null,
    'shop_id'   => $shop_id,
    'from_date' => $from_date !== '' ? $from_date : null,
    'to_date'   => $to_date !== '' ? $to_date : null
];


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Leads retrieved successfully.',
    [
        'leads'      => $leads,
        'pagination' => $pagination,
        'filters'    => $filters
    ],
    200
);