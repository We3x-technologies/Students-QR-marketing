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
// BEARER TOKEN
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
// TOKEN VALIDATION
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
// FROM DATE
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
// DEFAULT ANALYTICS PERIOD
// ============================================================
//
// If no date is supplied:
// current month is used for chart analytics.
//
// Overall summary remains all-time.
//

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
// PERIOD DATETIME
// ============================================================

$period_start =
    $from_date . ' 00:00:00';

$period_end =
    $to_date . ' 23:59:59';


// ============================================================
// DASHBOARD DATA VARIABLES
// ============================================================

$summary = [];

$lead_status_chart = [];

$course_chart = [];

$training_mode_chart = [];

$current_status_chart = [];

$qr_scan_shop_chart = [];

$daily_scan_chart = [];

$daily_lead_chart = [];

$shop_lead_chart = [];

$recent_leads = [];

$recent_scans = [];


// ============================================================
// 1. OVERALL SUMMARY
// ============================================================

try {

    // --------------------------------------------------------
    // TOTAL SHOPS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM shops
    ");

    $total_shops =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // ACTIVE SHOPS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM shops
        WHERE status = 'active'
    ");

    $active_shops =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // INACTIVE SHOPS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM shops
        WHERE status = 'inactive'
    ");

    $inactive_shops =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // TOTAL STUDENTS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM students
    ");

    $total_students =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // TOTAL LEADS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM student_leads
    ");

    $total_leads =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // NEED TO CONTACT
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM student_leads
        WHERE status = 'need_to_contact'
    ");

    $need_to_contact =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // CONVERTED
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM student_leads
        WHERE status = 'converted'
    ");

    $converted =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // REJECTED
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM student_leads
        WHERE status = 'rejected'
    ");

    $rejected =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // TOTAL QR SCANS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM student_registration_tokens
    ");

    $total_qr_scans =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // TODAY QR SCANS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM student_registration_tokens
        WHERE created_at >= CURDATE()
        AND created_at < DATE_ADD(
            CURDATE(),
            INTERVAL 1 DAY
        )
    ");

    $today_qr_scans =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // TODAY LEADS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM student_leads
        WHERE created_at >= CURDATE()
        AND created_at < DATE_ADD(
            CURDATE(),
            INTERVAL 1 DAY
        )
    ");

    $today_leads =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // TODAY CONVERSIONS
    // --------------------------------------------------------

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM student_leads
        WHERE status = 'converted'
        AND converted_at >= CURDATE()
        AND converted_at < DATE_ADD(
            CURDATE(),
            INTERVAL 1 DAY
        )
    ");

    $today_conversions =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // PERIOD LEADS
    // --------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE created_at BETWEEN ? AND ?
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $period_leads =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // PERIOD SCANS
    // --------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_registration_tokens
        WHERE created_at BETWEEN ? AND ?
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $period_scans =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // PERIOD CONVERSIONS
    // --------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_leads
        WHERE status = 'converted'
        AND converted_at BETWEEN ? AND ?
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $period_conversions =
        (int) $stmt->fetchColumn();


    // --------------------------------------------------------
    // CONVERSION RATE
    // --------------------------------------------------------

    $conversion_rate = 0;

    if ($total_leads > 0) {

        $conversion_rate =
            round(
                (
                    $converted /
                    $total_leads
                ) * 100,
                2
            );
    }


    // --------------------------------------------------------
    // PERIOD CONVERSION RATE
    // --------------------------------------------------------

    $period_conversion_rate = 0;

    if ($period_leads > 0) {

        $period_conversion_rate =
            round(
                (
                    $period_conversions /
                    $period_leads
                ) * 100,
                2
            );
    }


    // --------------------------------------------------------
    // SUMMARY
    // --------------------------------------------------------

    $summary = [

        'shops' => [

            'total' =>
                $total_shops,

            'active' =>
                $active_shops,

            'inactive' =>
                $inactive_shops
        ],

        'students' => [

            'total' =>
                $total_students
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
                $conversion_rate
        ],

        'qr_scans' => [

            'total' =>
                $total_qr_scans,

            'today' =>
                $today_qr_scans
        ],

        'today' => [

            'leads' =>
                $today_leads,

            'conversions' =>
                $today_conversions,

            'qr_scans' =>
                $today_qr_scans
        ],

        'selected_period' => [

            'from_date' =>
                $from_date,

            'to_date' =>
                $to_date,

            'leads' =>
                $period_leads,

            'qr_scans' =>
                $period_scans,

            'conversions' =>
                $period_conversions,

            'conversion_rate' =>
                $period_conversion_rate
        ]
    ];

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve dashboard summary.',
        [],
        500
    );
}


// ============================================================
// 2. LEAD STATUS CHART
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) AS total
        FROM student_leads
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $status_counts = [

        'need_to_contact' => 0,

        'converted' => 0,

        'rejected' => 0
    ];


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


    $lead_status_chart = [

        'type' =>
            'doughnut',

        'labels' => [

            'Need To Contact',

            'Converted',

            'Rejected'
        ],

        'datasets' => [

            [

                'label' =>
                    'Leads',

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

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve lead status chart data.',
        [],
        500
    );
}


// ============================================================
// 3. COURSE-WISE CHART
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            course_applied,
            COUNT(*) AS total
        FROM students
        WHERE created_at BETWEEN ? AND ?
        GROUP BY course_applied
        ORDER BY total DESC
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $course_labels = [];

    $course_values = [];


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


    foreach ($rows as $row) {

        $course_key =
            $row['course_applied'];

        $course_labels[] =
            isset(
                $course_names[
                    $course_key
                ]
            )
                ? $course_names[
                    $course_key
                ]
                : $course_key;

        $course_values[] =
            (int) $row['total'];
    }


    $course_chart = [

        'type' =>
            'bar',

        'labels' =>
            $course_labels,

        'datasets' => [

            [

                'label' =>
                    'Students',

                'data' =>
                    $course_values
            ]
        ]
    ];

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve course chart data.',
        [],
        500
    );
}


// ============================================================
// 4. TRAINING MODE CHART
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            training_mode,
            COUNT(*) AS total
        FROM students
        WHERE created_at BETWEEN ? AND ?
        GROUP BY training_mode
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $training_counts = [

        'classroom' => 0,

        'online' => 0,

        'hybrid' => 0
    ];


    foreach ($rows as $row) {

        if (
            isset(
                $training_counts[
                    $row['training_mode']
                ]
            )
        ) {

            $training_counts[
                $row['training_mode']
            ] =
                (int) $row['total'];
        }
    }


    $training_mode_chart = [

        'type' =>
            'pie',

        'labels' => [

            'Classroom',

            'Online',

            'Hybrid'
        ],

        'datasets' => [

            [

                'label' =>
                    'Training Mode',

                'data' => [

                    $training_counts[
                        'classroom'
                    ],

                    $training_counts[
                        'online'
                    ],

                    $training_counts[
                        'hybrid'
                    ]
                ]
            ]
        ]
    ];

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve training mode chart data.',
        [],
        500
    );
}


// ============================================================
// 5. CURRENT STUDENT STATUS CHART
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT
            current_status,
            COUNT(*) AS total
        FROM students
        WHERE created_at BETWEEN ? AND ?
        GROUP BY current_status
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $current_status_counts = [

        'student' => 0,

        'working' => 0,

        'job_seeker' => 0,

        'other' => 0
    ];


    foreach ($rows as $row) {

        if (
            isset(
                $current_status_counts[
                    $row['current_status']
                ]
            )
        ) {

            $current_status_counts[
                $row['current_status']
            ] =
                (int) $row['total'];
        }
    }


    $current_status_chart = [

        'type' =>
            'doughnut',

        'labels' => [

            'Student',

            'Working',

            'Job Seeker',

            'Other'
        ],

        'datasets' => [

            [

                'label' =>
                    'Students',

                'data' => [

                    $current_status_counts[
                        'student'
                    ],

                    $current_status_counts[
                        'working'
                    ],

                    $current_status_counts[
                        'job_seeker'
                    ],

                    $current_status_counts[
                        'other'
                    ]
                ]
            ]
        ]
    ];

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve student status chart data.',
        [],
        500
    );
}


// ============================================================
// 6. QR SCANS BY SHOP
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT

            sh.id AS shop_id,

            sh.shop_name,

            sh.qr_code,

            COUNT(srt.id) AS total_scans,

            MAX(srt.created_at) AS last_scan_at

        FROM shops sh

        LEFT JOIN student_registration_tokens srt

            ON srt.shop_id = sh.id

            AND srt.created_at BETWEEN ? AND ?

        GROUP BY

            sh.id,
            sh.shop_name,
            sh.qr_code

        ORDER BY
            total_scans DESC,
            last_scan_at DESC

        LIMIT 20
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $qr_shop_labels = [];

    $qr_shop_values = [];

    $qr_shop_details = [];


    foreach ($rows as $row) {

        $qr_shop_labels[] =
            $row['shop_name'];

        $qr_shop_values[] =
            (int) $row['total_scans'];


        $qr_shop_details[] = [

            'shop_id' =>
                (int) $row['shop_id'],

            'shop_name' =>
                $row['shop_name'],

            'qr_slug' =>
                $row['qr_code'],

            'total_scans' =>
                (int) $row['total_scans'],

            'last_scan_at' =>
                $row['last_scan_at']
        ];
    }


    $qr_scan_shop_chart = [

        'type' =>
            'bar',

        'labels' =>
            $qr_shop_labels,

        'datasets' => [

            [

                'label' =>
                    'QR Scans',

                'data' =>
                    $qr_shop_values
            ]
        ]
    ];

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve QR scan chart data.',
        [],
        500
    );
}


// ============================================================
// 7. DAILY QR SCAN TREND
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT

            DATE(created_at) AS scan_date,

            COUNT(*) AS total

        FROM student_registration_tokens

        WHERE created_at BETWEEN ? AND ?

        GROUP BY DATE(created_at)

        ORDER BY scan_date ASC
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $daily_scan_labels = [];

    $daily_scan_values = [];


    foreach ($rows as $row) {

        $daily_scan_labels[] =
            $row['scan_date'];

        $daily_scan_values[] =
            (int) $row['total'];
    }


    $daily_scan_chart = [

        'type' =>
            'line',

        'labels' =>
            $daily_scan_labels,

        'datasets' => [

            [

                'label' =>
                    'QR Scans',

                'data' =>
                    $daily_scan_values,

                'fill' =>
                    false
            ]
        ]
    ];

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve daily QR scan data.',
        [],
        500
    );
}


// ============================================================
// 8. DAILY LEAD TREND
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT

            DATE(created_at) AS lead_date,

            COUNT(*) AS total

        FROM student_leads

        WHERE created_at BETWEEN ? AND ?

        GROUP BY DATE(created_at)

        ORDER BY lead_date ASC
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $daily_lead_labels = [];

    $daily_lead_values = [];


    foreach ($rows as $row) {

        $daily_lead_labels[] =
            $row['lead_date'];

        $daily_lead_values[] =
            (int) $row['total'];
    }


    $daily_lead_chart = [

        'type' =>
            'line',

        'labels' =>
            $daily_lead_labels,

        'datasets' => [

            [

                'label' =>
                    'Leads',

                'data' =>
                    $daily_lead_values,

                'fill' =>
                    false
            ]
        ]
    ];

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve daily lead data.',
        [],
        500
    );
}


// ============================================================
// 9. SHOP-WISE LEAD COUNT
// ============================================================

try {

    $stmt = $pdo->prepare("
        SELECT

            sh.id AS shop_id,

            sh.shop_name,

            COUNT(sl.id) AS total_leads,

            SUM(
                CASE
                    WHEN sl.status = 'need_to_contact'
                    THEN 1
                    ELSE 0
                END
            ) AS need_to_contact,

            SUM(
                CASE
                    WHEN sl.status = 'converted'
                    THEN 1
                    ELSE 0
                END
            ) AS converted,

            SUM(
                CASE
                    WHEN sl.status = 'rejected'
                    THEN 1
                    ELSE 0
                END
            ) AS rejected

        FROM shops sh

        LEFT JOIN student_leads sl

            ON sl.shop_id = sh.id

            AND sl.created_at BETWEEN ? AND ?

        GROUP BY

            sh.id,
            sh.shop_name

        ORDER BY
            total_leads DESC

        LIMIT 20
    ");

    $stmt->execute([
        $period_start,
        $period_end
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $shop_lead_labels = [];

    $shop_lead_values = [];

    $shop_lead_details = [];


    foreach ($rows as $row) {

        $shop_lead_labels[] =
            $row['shop_name'];

        $shop_lead_values[] =
            (int) $row['total_leads'];


        $shop_lead_details[] = [

            'shop_id' =>
                (int) $row['shop_id'],

            'shop_name' =>
                $row['shop_name'],

            'total_leads' =>
                (int) $row['total_leads'],

            'need_to_contact' =>
                (int) $row['need_to_contact'],

            'converted' =>
                (int) $row['converted'],

            'rejected' =>
                (int) $row['rejected']
        ];
    }


    $shop_lead_chart = [

        'type' =>
            'bar',

        'labels' =>
            $shop_lead_labels,

        'datasets' => [

            [

                'label' =>
                    'Leads',

                'data' =>
                    $shop_lead_values
            ]
        ]
    ];

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve shop lead data.',
        [],
        500
    );
}


// ============================================================
// 10. RECENT LEADS
// ============================================================

try {

    $stmt = $pdo->query("
        SELECT

            sl.id AS lead_id,

            sl.status,

            sl.admin_remarks,

            sl.created_at,

            sl.updated_at,

            sh.id AS shop_id,

            sh.shop_name,

            s.id AS student_id,

            s.name AS student_name,

            s.phone AS student_phone,

            s.course_applied

        FROM student_leads sl

        INNER JOIN shops sh
            ON sh.id = sl.shop_id

        INNER JOIN students s
            ON s.id = sl.student_id

        ORDER BY
            sl.created_at DESC

        LIMIT 10
    ");

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach ($rows as $row) {

        $recent_leads[] = [

            'lead_id' =>
                (int) $row['lead_id'],

            'shop' => [

                'id' =>
                    (int) $row['shop_id'],

                'name' =>
                    $row['shop_name']
            ],

            'student' => [

                'id' =>
                    (int) $row['student_id'],

                'name' =>
                    $row['student_name'],

                'phone' =>
                    $row['student_phone'],

                'course' =>
                    $row['course_applied']
            ],

            'status' =>
                $row['status'],

            'admin_remarks' =>
                $row['admin_remarks'],

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
// 11. RECENT QR SCANS
// ============================================================

try {

    $stmt = $pdo->query("
        SELECT

            srt.id AS scan_id,

            srt.shop_id,

            srt.created_at AS scanned_at,

            srt.expires_at,

            srt.used_at,

            sh.shop_name,

            sh.qr_code

        FROM student_registration_tokens srt

        INNER JOIN shops sh
            ON sh.id = srt.shop_id

        ORDER BY
            srt.created_at DESC

        LIMIT 10
    ");

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach ($rows as $row) {

        $recent_scans[] = [

            'scan_id' =>
                (int) $row['scan_id'],

            'shop' => [

                'id' =>
                    (int) $row['shop_id'],

                'name' =>
                    $row['shop_name']
            ],

            'qr_slug' =>
                $row['qr_code'],

            'scanned_at' =>
                $row['scanned_at'],

            'token_expires_at' =>
                $row['expires_at'],

            'token_used_at' =>
                $row['used_at']
        ];
    }

} catch (PDOException $e) {

    response(
        false,
        'Failed to retrieve recent QR scans.',
        [],
        500
    );
}


// ============================================================
// 12. DASHBOARD META
// ============================================================

$dashboard_meta = [

    'analytics_from' =>
        $from_date,

    'analytics_to' =>
        $to_date,

    'analytics_period' =>
        'selected_date_range',

    'timezone' =>
        date_default_timezone_get(),

    'generated_at' =>
        date('Y-m-d H:i:s')
];


// ============================================================
// 13. FINAL RESPONSE
// ============================================================

response(
    true,
    'Admin dashboard data retrieved successfully.',
    [

        // ====================================================
        // DASHBOARD META
        // ====================================================

        'meta' =>
            $dashboard_meta,


        // ====================================================
        // SUMMARY CARDS
        // ====================================================

        'summary' =>
            $summary,


        // ====================================================
        // CHARTS
        // ====================================================

        'charts' => [

            'lead_status' =>
                $lead_status_chart,

            'courses' =>
                $course_chart,

            'training_modes' =>
                $training_mode_chart,

            'current_student_status' =>
                $current_status_chart,

            'qr_scans_by_shop' =>
                $qr_scan_shop_chart,

            'daily_qr_scans' =>
                $daily_scan_chart,

            'daily_leads' =>
                $daily_lead_chart,

            'leads_by_shop' =>
                $shop_lead_chart
        ],


        // ====================================================
        // TABLE DATA
        // ====================================================

        'tables' => [

            'shops_qr_summary' =>
                $qr_shop_details,

            'shops_lead_summary' =>
                $shop_lead_details,

            'recent_leads' =>
                $recent_leads,

            'recent_qr_scans' =>
                $recent_scans
        ]
    ],
    200
);