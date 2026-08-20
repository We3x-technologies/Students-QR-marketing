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
// SHOP ROLE CHECK
// ============================================================

if (
    !isset($payload['role']) ||
    $payload['role'] !== SHOP_ROLE
) {

    response(
        false,
        'Shop owner access required.',
        [],
        403
    );
}



$shop_id = null;

if (isset($payload['user_id'])) {

    $shop_id = $payload['user_id'];

} elseif (isset($payload['id'])) {

    $shop_id = $payload['id'];

} elseif (isset($payload['sub'])) {

    $shop_id = $payload['sub'];
}


// ============================================================
// SHOP ID VALIDATION
// ============================================================

if (
    $shop_id === null ||
    !preg_match('/^[0-9]+$/', (string) $shop_id)
) {

    response(
        false,
        'Invalid shop information in token.',
        [],
        401
    );
}


$shop_id = (int) $shop_id;


if ($shop_id < 1) {

    response(
        false,
        'Invalid shop information in token.',
        [],
        401
    );
}


// ============================================================
// VERIFY SHOP ACCOUNT
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            shop_name,
            owner_name,
            phone,
            status
        FROM shops
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $shop_id
    ]);

    $shop = $stmt->fetch();

} catch (PDOException $e) {

    response(
        false,
        'Failed to validate shop account.',
        [],
        500
    );
}


// ============================================================
// SHOP NOT FOUND
// ============================================================

if (!$shop) {

    response(
        false,
        'Shop account not found.',
        [],
        401
    );
}


// ============================================================
// SHOP STATUS CHECK
// ============================================================

if ($shop['status'] !== 'active') {

    response(
        false,
        'Shop account is inactive.',
        [],
        403
    );
}


// ============================================================
// PAGINATION - PAGE
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
// PAGINATION - LIMIT
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

    if (
        !in_array(
            $status,
            $allowed_statuses,
            true
        )
    ) {

        response(
            false,
            'Invalid lead status. Allowed values: need_to_contact, converted, rejected.',
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
// WHERE CONDITIONS
// ============================================================

$where = [];

$params = [];


// ============================================================
// ALWAYS FILTER BY LOGGED-IN SHOP
// ============================================================

$where[] = 'sl.shop_id = ?';

$params[] = $shop_id;


// ============================================================
// SEARCH
// ============================================================

if ($search !== '') {

    $where[] = "
        (
            s.name LIKE ?
            OR s.phone LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
}


// ============================================================
// STATUS
// ============================================================

if ($status !== '') {

    $where[] = 'sl.status = ?';

    $params[] = $status;
}


// ============================================================
// FROM DATE
// ============================================================

if ($from_date !== '') {

    $where[] = 'sl.created_at >= ?';

    $params[] = $from_date . ' 00:00:00';
}


// ============================================================
// TO DATE
// ============================================================

if ($to_date !== '') {

    $where[] = 'sl.created_at <= ?';

    $params[] = $to_date . ' 23:59:59';
}


// ============================================================
// WHERE SQL
// ============================================================

$where_sql = 'WHERE ' . implode(
    ' AND ',
    $where
);


// ============================================================
// TOTAL COUNT
// ============================================================

$count_sql = "
    SELECT COUNT(*)

    FROM student_leads sl

    INNER JOIN students s
        ON s.id = sl.student_id

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
// REQUESTED PAGE VALIDATION
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
        -- STUDENT DETAILS
        -- ====================================================

        s.id AS student_id,

        s.name AS student_name,

        s.phone AS student_phone

    FROM student_leads sl

    INNER JOIN students s
        ON s.id = sl.student_id

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

            'id' =>
                (int) $row['lead_id'],

            'status' =>
                $row['lead_status'],

            'admin_remarks' =>
                $row['admin_remarks'],

            'contacted_at' =>
                $row['contacted_at'],

            'converted_at' =>
                $row['converted_at'],

            'rejected_at' =>
                $row['rejected_at'],

            'created_at' =>
                $row['lead_created_at'],

            'updated_at' =>
                $row['lead_updated_at']
        ],


        // ====================================================
        // STUDENT
        // ====================================================
        //
        // ONLY NAME + PHONE.
        //

        'student' => [

            'id' =>
                (int) $row['student_id'],

            'name' =>
                $row['student_name'],

            'phone' =>
                $row['student_phone']
        ]
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

    'search' =>
        $search !== ''
            ? $search
            : null,

    'status' =>
        $status !== ''
            ? $status
            : null,

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
    'Shop leads retrieved successfully.',
    [

        'shop' => [

            'id' =>
                (int) $shop['id'],

            'shop_name' =>
                $shop['shop_name']
        ],

        'leads' =>
            $leads,

        'pagination' =>
            $pagination,

        'filters' =>
            $filters
    ],
    200
);