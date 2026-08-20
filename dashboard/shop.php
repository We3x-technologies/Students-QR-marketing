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

        $authorization =
            trim($_SERVER['HTTP_AUTHORIZATION']);

    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {

        $authorization =
            trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
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
// TOKEN EMPTY VALIDATION
// ============================================================

if ($token === '') {

    response(
        false,
        'Bearer token is required.',
        [],
        401
    );
}


// ============================================================
// JWT VERIFY
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


// ============================================================
// JWT PAYLOAD VALIDATION
// ============================================================

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
// JWT ROLE VALIDATION
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


// ============================================================
// JWT USER ID VALIDATION
// ============================================================
//
// IMPORTANT:
// jwt.php stores shop ID as:
//
// user_id
//
// NOT:
//
// id
//

if (
    !isset($payload['user_id']) ||
    !is_numeric($payload['user_id'])
) {

    response(
        false,
        'Invalid shop information in token.',
        [],
        401
    );
}


$shop_id = (int) $payload['user_id'];


// ============================================================
// SHOP ID VALIDATION
// ============================================================

if ($shop_id <= 0) {

    response(
        false,
        'Invalid shop ID.',
        [],
        401
    );
}


// ============================================================
// DATE FILTER VALIDATION
// ============================================================

$from_date = '';

$to_date = '';


// ============================================================
// FROM DATE
// ============================================================

if (
    isset($_GET['from_date']) &&
    trim($_GET['from_date']) !== ''
) {

    $from_date =
        trim((string) $_GET['from_date']);

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

    $from_object =
        DateTime::createFromFormat(
            'Y-m-d',
            $from_date
        );

    if (
        !$from_object ||
        $from_object->format('Y-m-d') !== $from_date
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

if (
    isset($_GET['to_date']) &&
    trim($_GET['to_date']) !== ''
) {

    $to_date =
        trim((string) $_GET['to_date']);

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

    $to_object =
        DateTime::createFromFormat(
            'Y-m-d',
            $to_date
        );

    if (
        !$to_object ||
        $to_object->format('Y-m-d') !== $to_date
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
// DEFAULT DATE RANGE
// ============================================================

if (
    $from_date === '' &&
    $to_date === ''
) {

    $from_date =
        date('Y-m-01');

    $to_date =
        date('Y-m-d');
}


// ============================================================
// IF ONLY FROM DATE IS PROVIDED
// ============================================================

if (
    $from_date !== '' &&
    $to_date === ''
) {

    $to_date =
        date('Y-m-d');
}


// ============================================================
// IF ONLY TO DATE IS PROVIDED
// ============================================================

if (
    $from_date === '' &&
    $to_date !== ''
) {

    $from_date =
        date('Y-m-01');
}


// ============================================================
// FINAL DATE RANGE
// ============================================================

if ($from_date > $to_date) {

    response(
        false,
        'Invalid date range.',
        [],
        422
    );
}


$period_start =
    $from_date . ' 00:00:00';

$period_end =
    $to_date . ' 23:59:59';


// ============================================================
// PAGINATION
// ============================================================

$page = 1;

$limit = 20;


// ============================================================
// PAGE
// ============================================================

if (
    isset($_GET['page']) &&
    $_GET['page'] !== ''
) {

    if (
        !ctype_digit(
            (string) $_GET['page']
        )
    ) {

        response(
            false,
            'Page must be a positive integer.',
            [],
            422
        );
    }

    $page =
        (int) $_GET['page'];

    if ($page <= 0) {

        response(
            false,
            'Page must be greater than zero.',
            [],
            422
        );
    }
}


// ============================================================
// LIMIT
// ============================================================

if (
    isset($_GET['limit']) &&
    $_GET['limit'] !== ''
) {

    if (
        !ctype_digit(
            (string) $_GET['limit']
        )
    ) {

        response(
            false,
            'Limit must be a positive integer.',
            [],
            422
        );
    }

    $limit =
        (int) $_GET['limit'];

    if (
        $limit <= 0 ||
        $limit > 100
    ) {

        response(
            false,
            'Limit must be between 1 and 100.',
            [],
            422
        );
    }
}


$offset =
    ($page - 1) * $limit;


// ============================================================
// VERIFY SHOP FROM DATABASE
// ============================================================

try {

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

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve shop details.',
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
        404
    );
}


// ============================================================
// SHOP STATUS
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
// SUMMARY VARIABLES
// ============================================================

$total_scans = 0;

$total_leads = 0;

$need_to_contact = 0;

$converted = 0;

$rejected = 0;

$today_scans = 0;

$today_leads = 0;

$today_converted = 0;

$period_scans = 0;

$period_leads = 0;

$period_converted = 0;


// ============================================================
// TOTAL QR SCANS
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_registration_tokens
        WHERE shop_id = ?
    ");

    $stmt->execute([
        $shop_id
    ]);

    $total_scans =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve QR scan count.',
        [],
        500
    );
}


// ============================================================
// TOTAL LEADS
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
    ");

    $stmt->execute([
        $shop_id
    ]);

    $total_leads =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve lead count.',
        [],
        500
    );
}


// ============================================================
// NEED TO CONTACT
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
        AND status = 'need_to_contact'
    ");

    $stmt->execute([
        $shop_id
    ]);

    $need_to_contact =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve pending leads.',
        [],
        500
    );
}


// ============================================================
// CONVERTED
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
        AND status = 'converted'
    ");

    $stmt->execute([
        $shop_id
    ]);

    $converted =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve converted leads.',
        [],
        500
    );
}


// ============================================================
// REJECTED
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
        AND status = 'rejected'
    ");

    $stmt->execute([
        $shop_id
    ]);

    $rejected =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve rejected leads.',
        [],
        500
    );
}


// ============================================================
// TODAY QR SCANS
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_registration_tokens
        WHERE shop_id = ?
        AND created_at >= CURDATE()
        AND created_at < DATE_ADD(
            CURDATE(),
            INTERVAL 1 DAY
        )
    ");

    $stmt->execute([
        $shop_id
    ]);

    $today_scans =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve today scan count.',
        [],
        500
    );
}


// ============================================================
// TODAY LEADS
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
        AND created_at >= CURDATE()
        AND created_at < DATE_ADD(
            CURDATE(),
            INTERVAL 1 DAY
        )
    ");

    $stmt->execute([
        $shop_id
    ]);

    $today_leads =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve today leads.',
        [],
        500
    );
}


// ============================================================
// TODAY CONVERTED
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
        AND status = 'converted'
        AND converted_at >= CURDATE()
        AND converted_at < DATE_ADD(
            CURDATE(),
            INTERVAL 1 DAY
        )
    ");

    $stmt->execute([
        $shop_id
    ]);

    $today_converted =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve today conversions.',
        [],
        500
    );
}


// ============================================================
// PERIOD QR SCANS
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_registration_tokens
        WHERE shop_id = ?
        AND created_at BETWEEN ? AND ?
    ");

    $stmt->execute([
        $shop_id,
        $period_start,
        $period_end
    ]);

    $period_scans =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve period scan count.',
        [],
        500
    );
}


// ============================================================
// PERIOD LEADS
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
        AND created_at BETWEEN ? AND ?
    ");

    $stmt->execute([
        $shop_id,
        $period_start,
        $period_end
    ]);

    $period_leads =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve period leads.',
        [],
        500
    );
}


// ============================================================
// PERIOD CONVERTED
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
        AND status = 'converted'
        AND converted_at BETWEEN ? AND ?
    ");

    $stmt->execute([
        $shop_id,
        $period_start,
        $period_end
    ]);

    $period_converted =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve period conversions.',
        [],
        500
    );
}


// ============================================================
// CONVERSION RATE
// ============================================================

$conversion_rate = 0;

if ($total_leads > 0) {

    $conversion_rate =
        round(
            ($converted / $total_leads) * 100,
            2
        );
}


// ============================================================
// PERIOD CONVERSION RATE
// ============================================================

$period_conversion_rate = 0;

if ($period_leads > 0) {

    $period_conversion_rate =
        round(
            ($period_converted / $period_leads) * 100,
            2
        );
}


// ============================================================
// LEAD STATUS CHART
// ============================================================

$status_counts = [

    'need_to_contact' => 0,

    'converted' => 0,

    'rejected' => 0
];


try {

    $stmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) AS total
        FROM student_leads
        WHERE shop_id = ?
        AND created_at BETWEEN ? AND ?
        GROUP BY status
    ");

    $stmt->execute([
        $shop_id,
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($rows as $row) {

        if (
            isset(
                $status_counts[
                    $row['status']
                ]
            )
        ) {

            $status_counts[
                $row['status']
            ] =
                (int) $row['total'];
        }
    }

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve status chart data.',
        [],
        500
    );
}


$lead_status_chart = [

    'type' => 'doughnut',

    'labels' => [

        'Need To Contact',

        'Converted',

        'Rejected'
    ],

    'datasets' => [

        [

            'label' => 'Leads',

            'data' => [

                $status_counts[
                    'need_to_contact'
                ],

                $status_counts[
                    'converted'
                ],

                $status_counts[
                    'rejected'
                ]
            ]
        ]
    ]
];


// ============================================================
// COURSE-WISE CHART
// ============================================================

$course_names = [

    'digital_marketing' =>
        'Digital Marketing',

    'mern_stack_development' =>
        'MERN Stack Development',

    'ui_ux_graphics_design' =>
        'UI / UX Graphics Design',

    'cyber_security' =>
        'Cyber Security',

    'tally_prime' =>
        'Tally Prime',

    'basics_computers' =>
        'Basics Computers',

    'ai_ml' =>
        'AI & ML',

    'cloud_computing' =>
        'Cloud Computing'
];


$course_labels = [];

$course_values = [];


try {

    $stmt = $pdo->prepare("
        SELECT
            s.course_applied,
            COUNT(sl.id) AS total
        FROM student_leads sl
        INNER JOIN students s
            ON s.id = sl.student_id
        WHERE sl.shop_id = ?
        AND sl.created_at BETWEEN ? AND ?
        GROUP BY s.course_applied
        ORDER BY total DESC
    ");

    $stmt->execute([
        $shop_id,
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($rows as $row) {

        $course =
            $row['course_applied'];

        $course_labels[] =
            isset($course_names[$course])
                ? $course_names[$course]
                : $course;

        $course_values[] =
            (int) $row['total'];
    }

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve course chart data.',
        [],
        500
    );
}


$course_chart = [

    'type' => 'bar',

    'labels' =>
        $course_labels,

    'datasets' => [

        [

            'label' => 'Leads',

            'data' =>
                $course_values
        ]
    ]
];


// ============================================================
// DAILY QR SCAN CHART
// ============================================================

$scan_labels = [];

$scan_values = [];


try {

    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) AS scan_date,
            COUNT(*) AS total
        FROM student_registration_tokens
        WHERE shop_id = ?
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY scan_date ASC
    ");

    $stmt->execute([
        $shop_id,
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($rows as $row) {

        $scan_labels[] =
            $row['scan_date'];

        $scan_values[] =
            (int) $row['total'];
    }

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve daily scan chart.',
        [],
        500
    );
}


$daily_scan_chart = [

    'type' => 'line',

    'labels' =>
        $scan_labels,

    'datasets' => [

        [

            'label' => 'QR Scans',

            'data' =>
                $scan_values,

            'fill' => false
        ]
    ]
];


// ============================================================
// DAILY LEAD CHART
// ============================================================

$lead_labels = [];

$lead_values = [];


try {

    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) AS lead_date,
            COUNT(*) AS total
        FROM student_leads
        WHERE shop_id = ?
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY lead_date ASC
    ");

    $stmt->execute([
        $shop_id,
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($rows as $row) {

        $lead_labels[] =
            $row['lead_date'];

        $lead_values[] =
            (int) $row['total'];
    }

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve daily lead chart.',
        [],
        500
    );
}


$daily_lead_chart = [

    'type' => 'line',

    'labels' =>
        $lead_labels,

    'datasets' => [

        [

            'label' => 'Leads',

            'data' =>
                $lead_values,

            'fill' => false
        ]
    ]
];


// ============================================================
// TOTAL STUDENT RECORDS FOR PAGINATION
// ============================================================

$total_student_records = 0;


try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE shop_id = ?
        AND created_at BETWEEN ? AND ?
    ");

    $stmt->execute([
        $shop_id,
        $period_start,
        $period_end
    ]);

    $total_student_records =
        (int) $stmt->fetchColumn();

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve student record count.',
        [],
        500
    );
}


// ============================================================
// STUDENT LEADS LIST
// ============================================================
//
// SHOP OWNER CAN SEE ONLY:
//
// student name
// student phone
// lead status
//
// No email.
// No gender.
// No location.
// No qualification.
// No institution.
// No current status.
// No course.
// No training mode.
// No declaration.
//

$students = [];


try {

    $stmt = $pdo->prepare("
        SELECT

            sl.id AS lead_id,

            s.id AS student_id,

            s.name AS student_name,

            s.phone AS student_phone,

            sl.status,

            sl.created_at,

            sl.updated_at

        FROM student_leads sl

        INNER JOIN students s
            ON s.id = sl.student_id

        WHERE sl.shop_id = ?

        AND sl.created_at BETWEEN ? AND ?

        ORDER BY
            sl.created_at DESC

        LIMIT ?
        OFFSET ?
    ");

    $stmt->bindValue(
        1,
        $shop_id,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        2,
        $period_start,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        3,
        $period_end,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        4,
        $limit,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        5,
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($rows as $row) {

        $students[] = [

            'lead_id' =>
                (int) $row['lead_id'],

            'student' => [

                'id' =>
                    (int) $row['student_id'],

                'name' =>
                    $row['student_name'],

                'phone' =>
                    $row['student_phone']
            ],

            'status' =>
                $row['status'],

            'created_at' =>
                $row['created_at'],

            'updated_at' =>
                $row['updated_at']
        ];
    }

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve student leads.',
        [],
        500
    );
}


// ============================================================
// PAGINATION
// ============================================================

$total_pages = 0;

if ($total_student_records > 0) {

    $total_pages =
        (int) ceil(
            $total_student_records /
            $limit
        );
}


// ============================================================
// RECENT LEADS
// ============================================================

$recent_leads = [];


try {

    $stmt = $pdo->prepare("
        SELECT

            sl.id AS lead_id,

            s.id AS student_id,

            s.name AS student_name,

            s.phone AS student_phone,

            sl.status,

            sl.created_at,

            sl.updated_at

        FROM student_leads sl

        INNER JOIN students s
            ON s.id = sl.student_id

        WHERE sl.shop_id = ?

        ORDER BY
            sl.created_at DESC

        LIMIT 10
    ");

    $stmt->execute([
        $shop_id
    ]);

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($rows as $row) {

        $recent_leads[] = [

            'lead_id' =>
                (int) $row['lead_id'],

            'student' => [

                'name' =>
                    $row['student_name'],

                'phone' =>
                    $row['student_phone']
            ],

            'status' =>
                $row['status'],

            'created_at' =>
                $row['created_at'],

            'updated_at' =>
                $row['updated_at']
        ];
    }

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve recent leads.',
        [],
        500
    );
}


// ============================================================
// LAST QR SCAN
// ============================================================

$last_scan = null;


try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            created_at,
            expires_at,
            used_at
        FROM student_registration_tokens
        WHERE shop_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");

    $stmt->execute([
        $shop_id
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if ($row) {

        $last_scan = [

            'scan_id' =>
                (int) $row['id'],

            'scanned_at' =>
                $row['created_at'],

            'token_expires_at' =>
                $row['expires_at'],

            'token_used_at' =>
                $row['used_at']
        ];
    }

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve last QR scan.',
        [],
        500
    );
}


// ============================================================
// SHOP DATA
// ============================================================
//
// PASSWORD IS NEVER RETURNED.
//

$shop_data = [

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

    'qr_slug' =>
        $shop['qr_code'],

    'status' =>
        $shop['status'],

    'last_login_at' =>
        $shop['last_login_at'],

    'created_at' =>
        $shop['created_at'],

    'updated_at' =>
        $shop['updated_at']
];


// ============================================================
// META
// ============================================================

$meta = [

    'analytics_from' =>
        $from_date,

    'analytics_to' =>
        $to_date,

    'page' =>
        $page,

    'limit' =>
        $limit,

    'total_records' =>
        $total_student_records,

    'total_pages' =>
        $total_pages,

    'generated_at' =>
        date('Y-m-d H:i:s')
];


// ============================================================
// FINAL RESPONSE
// ============================================================

response(
    true,
    'Shop dashboard data retrieved successfully.',
    [

        // ----------------------------------------------------
        // SHOP DETAILS
        // ----------------------------------------------------

        'shop' =>
            $shop_data,


        // ----------------------------------------------------
        // DASHBOARD META
        // ----------------------------------------------------

        'meta' =>
            $meta,


        // ----------------------------------------------------
        // SUMMARY
        // ----------------------------------------------------

        'summary' => [

            'qr_scans' => [

                'total' =>
                    $total_scans,

                'today' =>
                    $today_scans,

                'selected_period' =>
                    $period_scans
            ],

            'leads' => [

                'total' =>
                    $total_leads,

                'need_to_contact' =>
                    $need_to_contact,

                'converted' =>
                    $converted,

                'rejected' =>
                    $rejected,

                'conversion_rate' =>
                    $conversion_rate,

                'selected_period' => [

                    'total' =>
                        $period_leads,

                    'converted' =>
                        $period_converted,

                    'conversion_rate' =>
                        $period_conversion_rate
                ]
            ],

            'today' => [

                'scans' =>
                    $today_scans,

                'leads' =>
                    $today_leads,

                'converted' =>
                    $today_converted
            ]
        ],


        // ----------------------------------------------------
        // CHART.JS DATA
        // ----------------------------------------------------

        'charts' => [

            'lead_status' =>
                $lead_status_chart,

            'course_wise_leads' =>
                $course_chart,

            'daily_qr_scans' =>
                $daily_scan_chart,

            'daily_leads' =>
                $daily_lead_chart
        ],


        // ----------------------------------------------------
        // LAST QR SCAN
        // ----------------------------------------------------

        'last_qr_scan' =>
            $last_scan,


        // ----------------------------------------------------
        // STUDENTS / LEADS
        // ----------------------------------------------------

        'students' => [

            'data' =>
                $students,

            'pagination' => [

                'current_page' =>
                    $page,

                'per_page' =>
                    $limit,

                'total_records' =>
                    $total_student_records,

                'total_pages' =>
                    $total_pages
            ]
        ],


        // ----------------------------------------------------
        // RECENT LEADS
        // ----------------------------------------------------

        'recent_leads' =>
            $recent_leads
    ],
    200
);