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
// AUTHORIZATION REQUIRED
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
// TOKEN EMPTY VALIDATION
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
// VERIFY JWT
// ============================================================

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
// ADMIN ROLE VALIDATION
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
// ADMIN ID VALIDATION
// ============================================================

if (
    !isset($payload['sub']) &&
    !isset($payload['user_id']) &&
    !isset($payload['id'])
) {

    response(
        false,
        'Invalid authentication payload.',
        [],
        401
    );
}


// ============================================================
// PAGE VALIDATION
// ============================================================

$page = 1;

if (
    isset($_GET['page']) &&
    $_GET['page'] !== ''
) {

    $page_input = trim(
        (string) $_GET['page']
    );

    if (
        !preg_match(
            '/^[0-9]+$/',
            $page_input
        )
    ) {

        response(
            false,
            'Invalid page number.',
            [],
            422
        );
    }

    $page = (int) $page_input;
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
// LIMIT VALIDATION
// ============================================================

$limit = 20;

if (
    isset($_GET['limit']) &&
    $_GET['limit'] !== ''
) {

    $limit_input = trim(
        (string) $_GET['limit']
    );

    if (
        !preg_match(
            '/^[0-9]+$/',
            $limit_input
        )
    ) {

        response(
            false,
            'Invalid limit.',
            [],
            422
        );
    }

    $limit = (int) $limit_input;
}


if (
    $limit < 1 ||
    $limit > 100
) {

    response(
        false,
        'Limit must be between 1 and 100.',
        [],
        422
    );
}


$offset = ($page - 1) * $limit;


// ============================================================
// SEARCH VALIDATION
// ============================================================

$search = '';

if (
    isset($_GET['search']) &&
    $_GET['search'] !== ''
) {

    $search = trim(
        (string) $_GET['search']
    );

    if ($search === '') {

        response(
            false,
            'Search value cannot be empty.',
            [],
            422
        );
    }

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
// SHOP ID FILTER
// ============================================================

$shop_id = null;

if (
    isset($_GET['shop_id']) &&
    $_GET['shop_id'] !== ''
) {

    $shop_id_input = trim(
        (string) $_GET['shop_id']
    );

    if (
        !preg_match(
            '/^[0-9]+$/',
            $shop_id_input
        )
    ) {

        response(
            false,
            'Invalid shop ID.',
            [],
            422
        );
    }

    $shop_id = (int) $shop_id_input;

    if ($shop_id <= 0) {

        response(
            false,
            'Shop ID must be greater than zero.',
            [],
            422
        );
    }
}


// ============================================================
// SHOP STATUS FILTER
// ============================================================

$status = '';

if (
    isset($_GET['status']) &&
    $_GET['status'] !== ''
) {

    $status = strtolower(
        trim((string) $_GET['status'])
    );

    if (
        !in_array(
            $status,
            [
                'active',
                'inactive'
            ],
            true
        )
    ) {

        response(
            false,
            'Invalid shop status. Allowed values: active, inactive.',
            [],
            422
        );
    }
}


// ============================================================
// FROM DATE VALIDATION
// ============================================================

$from_date = '';

if (
    isset($_GET['from_date']) &&
    $_GET['from_date'] !== ''
) {

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

    $from_date_object = DateTime::createFromFormat(
        'Y-m-d',
        $from_date
    );

    if (
        !$from_date_object ||
        $from_date_object->format('Y-m-d') !== $from_date
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
// TO DATE VALIDATION
// ============================================================

$to_date = '';

if (
    isset($_GET['to_date']) &&
    $_GET['to_date'] !== ''
) {

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

    $to_date_object = DateTime::createFromFormat(
        'Y-m-d',
        $to_date
    );

    if (
        !$to_date_object ||
        $to_date_object->format('Y-m-d') !== $to_date
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
// BUILD SHOP FILTERS
// ============================================================

$shop_conditions = [];

$shop_params = [];


// ============================================================
// SEARCH FILTER
// ============================================================

if ($search !== '') {

    $shop_conditions[] = "
        (
            shop_name LIKE ?
            OR owner_name LIKE ?
            OR phone LIKE ?
            OR username LIKE ?
            OR email LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $shop_params[] = $search_value;
    $shop_params[] = $search_value;
    $shop_params[] = $search_value;
    $shop_params[] = $search_value;
    $shop_params[] = $search_value;
}


// ============================================================
// SHOP ID FILTER
// ============================================================

if ($shop_id !== null) {

    $shop_conditions[] = 'id = ?';

    $shop_params[] = $shop_id;
}


// ============================================================
// SHOP STATUS FILTER
// ============================================================

if ($status !== '') {

    $shop_conditions[] = 'status = ?';

    $shop_params[] = $status;
}


// ============================================================
// SHOP WHERE SQL
// ============================================================

$shop_where_sql = '';

if (!empty($shop_conditions)) {

    $shop_where_sql =
        'WHERE ' .
        implode(
            ' AND ',
            $shop_conditions
        );
}


// ============================================================
// TOTAL SHOP COUNT
// ============================================================

$count_sql = "
    SELECT COUNT(*)
    FROM shops
    $shop_where_sql
";


try {

    $stmt = $pdo->prepare(
        $count_sql
    );

    $stmt->execute(
        $shop_params
    );

    $total_shops = (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve shop count.',
        [],
        500
    );
}


// ============================================================
// TOTAL PAGES
// ============================================================

$total_pages = $total_shops > 0
    ? (int) ceil(
        $total_shops / $limit
    )
    : 0;


// ============================================================
// PAGE EXISTS VALIDATION
// ============================================================

if (
    $total_shops > 0 &&
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
// SCAN DATE CONDITIONS
// ============================================================

$scan_conditions = [
    'srt.shop_id = sh.id'
];

$scan_params = [];


// ============================================================
// FROM DATE
// ============================================================

if ($from_date !== '') {

    $scan_conditions[] =
        'srt.created_at >= ?';

    $scan_params[] =
        $from_date . ' 00:00:00';
}


// ============================================================
// TO DATE
// ============================================================

if ($to_date !== '') {

    $scan_conditions[] =
        'srt.created_at <= ?';

    $scan_params[] =
        $to_date . ' 23:59:59';
}


// ============================================================
// SCAN WHERE SQL
// ============================================================

$scan_where_sql =
    implode(
        ' AND ',
        $scan_conditions
    );




$sql = "
    SELECT

        sh.id AS shop_id,

        sh.shop_name,

        sh.owner_name,

        sh.phone AS shop_phone,

        sh.email AS shop_email,

        sh.address AS shop_address,

        sh.username AS shop_username,

        sh.qr_code,

        sh.status AS shop_status,

        sh.last_login_at,

        sh.created_at AS shop_created_at,

        sh.updated_at AS shop_updated_at,


        (
            SELECT COUNT(*)
            FROM student_registration_tokens srt
            WHERE $scan_where_sql
        ) AS total_scans,


        (
            SELECT MAX(srt.created_at)
            FROM student_registration_tokens srt
            WHERE $scan_where_sql
        ) AS last_scan_at


    FROM shops sh

    $shop_where_sql


    ORDER BY

        CASE
            WHEN (
                SELECT MAX(srt2.created_at)
                FROM student_registration_tokens srt2
                WHERE srt2.shop_id = sh.id
            ) IS NULL
            THEN 1
            ELSE 0
        END ASC,

        (
            SELECT MAX(srt3.created_at)
            FROM student_registration_tokens srt3
            WHERE srt3.shop_id = sh.id
        ) DESC,

        sh.id DESC


    LIMIT $limit
    OFFSET $offset
";


 
$query_params = array_merge(

    $scan_params,

    $scan_params,

    $shop_params

);


try {

    $stmt = $pdo->prepare(
        $sql
    );

    $stmt->execute(
        $query_params
    );

    $rows = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve QR scan details.',
        [],
        500
    );
}


// ============================================================
// FORMAT RESPONSE
// ============================================================

$shops = [];

foreach ($rows as $row) {

    $shops[] = [

        // ====================================================
        // SHOP DETAILS
        // ====================================================

        'shop' => [

            'id' =>
                (int) $row['shop_id'],

            'shop_name' =>
                $row['shop_name'],

            'owner_name' =>
                $row['owner_name'],

            'phone' =>
                $row['shop_phone'],

            'email' =>
                $row['shop_email'],

            'address' =>
                $row['shop_address'],

            'username' =>
                $row['shop_username'],

            'qr_slug' =>
                $row['qr_code'],

            'status' =>
                $row['shop_status'],

            'last_login_at' =>
                $row['last_login_at'],

            'created_at' =>
                $row['shop_created_at'],

            'updated_at' =>
                $row['shop_updated_at']
        ],


        // ====================================================
        // QR SCAN DETAILS
        // ====================================================

        'qr_scan' => [

            'total_scans' =>
                (int) $row['total_scans'],

            'last_scan_at' =>
                $row['last_scan_at']
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

    'total_shops' =>
        $total_shops,

    'total_pages' =>
        $total_pages,

    'has_previous' =>
        ($page > 1),

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

    'shop_id' =>
        $shop_id !== null
            ? $shop_id
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
    'QR scan details retrieved successfully.',
    [

        'shops' =>
            $shops,

        'pagination' =>
            $pagination,

        'filters' =>
            $filters
    ],
    200
);