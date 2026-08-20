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
// PAGINATION
// ============================================================

$page = isset($_GET['page'])
    ? trim($_GET['page'])
    : '1';

$limit = isset($_GET['limit'])
    ? trim($_GET['limit'])
    : '10';


// ============================================================
// PAGE VALIDATION
// ============================================================

if (!ctype_digit($page) || (int) $page < 1) {

    response(
        false,
        'Page must be a valid positive integer.',
        [],
        422
    );
}

$page = (int) $page;


// ============================================================
// LIMIT VALIDATION
// ============================================================

if (!ctype_digit($limit) || (int) $limit < 1) {

    response(
        false,
        'Limit must be a valid positive integer.',
        [],
        422
    );
}

$limit = (int) $limit;


// Maximum 100 records per request

if ($limit > 100) {

    response(
        false,
        'Limit cannot exceed 100.',
        [],
        422
    );
}


// ============================================================
// SEARCH VALIDATION
// ============================================================

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

if (strlen($search) > 150) {

    response(
        false,
        'Search cannot exceed 150 characters.',
        [],
        422
    );
}


// ============================================================
// STATUS FILTER VALIDATION
// ============================================================

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

if ($status !== '') {

    if (!in_array($status, ['active', 'inactive'], true)) {

        response(
            false,
            'Status must be either active or inactive.',
            [],
            422
        );
    }
}


// ============================================================
// OFFSET
// ============================================================

$offset = ($page - 1) * $limit;


// ============================================================
// BUILD WHERE CONDITION
// ============================================================

$where = [];

$params = [];


// ============================================================
// SEARCH CONDITION
// ============================================================

if ($search !== '') {

    $where[] = "
        (
            shop_name LIKE ?
            OR owner_name LIKE ?
            OR phone LIKE ?
            OR username LIKE ?
        )
    ";

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

    $where[] = "status = ?";

    $params[] = $status;
}


// ============================================================
// WHERE QUERY
// ============================================================

$where_sql = '';

if (!empty($where)) {

    $where_sql = 'WHERE ' . implode(' AND ', $where);
}


// ============================================================
// TOTAL SHOP COUNT
// ============================================================

$count_sql = "
    SELECT COUNT(*)
    FROM shops
    $where_sql
";

$stmt = $pdo->prepare($count_sql);

$stmt->execute($params);

$total_shops = (int) $stmt->fetchColumn();


// ============================================================
// TOTAL PAGES
// ============================================================

$total_pages = $total_shops > 0
    ? (int) ceil($total_shops / $limit)
    : 0;


// ============================================================
// PAGE OUT OF RANGE
// ============================================================

if ($total_pages > 0 && $page > $total_pages) {

    response(
        false,
        'Page number is out of range.',
        [],
        422
    );
}


// ============================================================
// FETCH SHOPS
// ============================================================

$sql = "
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
    $where_sql
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$shops = $stmt->fetchAll();


// ============================================================
// FORMAT SHOP DATA
// ============================================================

$shop_list = [];

foreach ($shops as $shop) {

    $shop_list[] = [
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
    ];
}


// ============================================================
// SUCCESS RESPONSE
// ============================================================

response(
    true,
    'Shop list fetched successfully.',
    [
        'shops' => $shop_list,

        'pagination' => [
            'current_page' => $page,
            'per_page'     => $limit,
            'total_shops'  => $total_shops,
            'total_pages'  => $total_pages
        ]
    ],
    200
);