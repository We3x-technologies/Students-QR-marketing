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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    response(
        false,
        'Only POST method is allowed.',
        [],
        405
    );
}


// ============================================================
// JSON INPUT
// ============================================================

$input = json_decode(
    file_get_contents('php://input'),
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
// REQUIRED FIELD HELPER
// ============================================================

function requiredField($input, $field, $label)
{
    if (
        !array_key_exists($field, $input) ||
        $input[$field] === null ||
        (is_string($input[$field]) && trim($input[$field]) === '')
    ) {

        response(
            false,
            $label . ' is required.',
            [],
            422
        );
    }
}


// ============================================================
// REQUIRED FIELDS
// ============================================================

requiredField(
    $input,
    'registration_token',
    'Registration token'
);

requiredField(
    $input,
    'name',
    'Full name'
);

requiredField(
    $input,
    'gender',
    'Gender'
);

requiredField(
    $input,
    'phone',
    'Mobile number'
);

requiredField(
    $input,
    'email',
    'Email address'
);

requiredField(
    $input,
    'current_location',
    'Current location'
);

requiredField(
    $input,
    'highest_qualification',
    'Highest qualification'
);

requiredField(
    $input,
    'current_status',
    'Current status'
);

requiredField(
    $input,
    'course_applied',
    'Course applied'
);

requiredField(
    $input,
    'training_mode',
    'Training mode'
);

requiredField(
    $input,
    'declaration',
    'Declaration'
);


// ============================================================
// INPUT VALUES
// ============================================================

$registration_token = trim(
    (string) $input['registration_token']
);

$name = trim(
    (string) $input['name']
);

$gender = trim(
    (string) $input['gender']
);

$phone = trim(
    (string) $input['phone']
);

$email = strtolower(
    trim((string) $input['email'])
);

$current_location = trim(
    (string) $input['current_location']
);

$highest_qualification = trim(
    (string) $input['highest_qualification']
);

$institution_name = isset($input['institution_name'])
    ? trim((string) $input['institution_name'])
    : null;

$current_status = trim(
    (string) $input['current_status']
);

$current_status_other = isset($input['current_status_other'])
    ? trim((string) $input['current_status_other'])
    : null;

$course_applied = trim(
    (string) $input['course_applied']
);

$training_mode = trim(
    (string) $input['training_mode']
);

$declaration = $input['declaration'];


// ============================================================
// REGISTRATION TOKEN VALIDATION
// ============================================================

if (
    strlen($registration_token) !== 64 ||
    !preg_match('/^[a-fA-F0-9]{64}$/', $registration_token)
) {

    response(
        false,
        'Invalid registration token.',
        [],
        422
    );
}


// ============================================================
// NAME VALIDATION
// ============================================================

if (strlen($name) < 2 || strlen($name) > 150) {

    response(
        false,
        'Full name must be between 2 and 150 characters.',
        [],
        422
    );
}


// Allow letters, spaces, dots, apostrophe and hyphen.

if (!preg_match(
    "/^[A-Za-zÀ-ÿ.'-]+(?: [A-Za-zÀ-ÿ.'-]+)*$/u",
    $name
)) {

    response(
        false,
        'Invalid full name format.',
        [],
        422
    );
}


// ============================================================
// GENDER VALIDATION
// ============================================================

$allowed_genders = [
    'male',
    'female',
    'other'
];

$gender_normalized = strtolower($gender);

if (!in_array($gender_normalized, $allowed_genders, true)) {

    response(
        false,
        'Invalid gender. Allowed values: male, female, other.',
        [],
        422
    );
}


// ============================================================
// PHONE VALIDATION
// ============================================================

// Indian 10 digit mobile number.
// Starts with 6, 7, 8 or 9.

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {

    response(
        false,
        'Invalid mobile number. Enter a valid 10-digit mobile number.',
        [],
        422
    );
}


// Reject numbers containing same digit 10 times.

if (preg_match('/^([0-9])\1{9}$/', $phone)) {

    response(
        false,
        'Invalid mobile number.',
        [],
        422
    );
}


// ============================================================
// EMAIL VALIDATION
// ============================================================

if (strlen($email) > 150) {

    response(
        false,
        'Email address is too long.',
        [],
        422
    );
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    response(
        false,
        'Invalid email address.',
        [],
        422
    );
}


// ============================================================
// CURRENT LOCATION VALIDATION
// ============================================================

if (
    strlen($current_location) < 2 ||
    strlen($current_location) > 255
) {

    response(
        false,
        'Current location must be between 2 and 255 characters.',
        [],
        422
    );
}


// ============================================================
// HIGHEST QUALIFICATION VALIDATION
// ============================================================

if (
    strlen($highest_qualification) < 2 ||
    strlen($highest_qualification) > 150
) {

    response(
        false,
        'Highest qualification must be between 2 and 150 characters.',
        [],
        422
    );
}


// ============================================================
// INSTITUTION NAME VALIDATION
// ============================================================

if (
    $institution_name !== null &&
    $institution_name !== ''
) {

    if (strlen($institution_name) > 255) {

        response(
            false,
            'Institution / College name is too long.',
            [],
            422
        );
    }
}


// ============================================================
// CURRENT STATUS VALIDATION
// ============================================================

$allowed_current_status = [
    'student',
    'working',
    'job_seeker',
    'other'
];

$current_status = strtolower($current_status);

if (!in_array($current_status, $allowed_current_status, true)) {

    response(
        false,
        'Invalid current status.',
        [],
        422
    );
}


// ============================================================
// CURRENT STATUS OTHER VALIDATION
// ============================================================

if ($current_status === 'other') {

    if (
        $current_status_other === null ||
        $current_status_other === ''
    ) {

        response(
            false,
            'Current status other value is required.',
            [],
            422
        );
    }

    if (strlen($current_status_other) > 150) {

        response(
            false,
            'Current status other value is too long.',
            [],
            422
        );
    }

} else {

    $current_status_other = null;
}


// ============================================================
// COURSE VALIDATION
// ============================================================

$allowed_courses = [
    'digital_marketing',
    'mern_stack_development',
    'ui_ux_graphics_design',
    'cyber_security',
    'tally_prime',
    'basics_computers',
    'ai_ml',
    'cloud_computing'
];

$course_applied = strtolower($course_applied);

if (!in_array($course_applied, $allowed_courses, true)) {

    response(
        false,
        'Invalid course selected.',
        [],
        422
    );
}


// ============================================================
// TRAINING MODE VALIDATION
// ============================================================

$allowed_training_modes = [
    'classroom',
    'online',
    'hybrid'
];

$training_mode = strtolower($training_mode);

if (!in_array($training_mode, $allowed_training_modes, true)) {

    response(
        false,
        'Invalid training mode. Allowed values: classroom, online, hybrid.',
        [],
        422
    );
}


// ============================================================
// DECLARATION VALIDATION
// ============================================================

$declaration_valid =
    $declaration === true ||
    $declaration === 1 ||
    $declaration === '1' ||
    $declaration === 'true';

if (!$declaration_valid) {

    response(
        false,
        'Declaration must be accepted.',
        [],
        422
    );
}

$declaration = 1;


// ============================================================
// HASH REGISTRATION TOKEN
// ============================================================

$token_hash = hash(
    'sha256',
    $registration_token
);


// ============================================================
// START TRANSACTION
// ============================================================

try {

    $pdo->beginTransaction();


    // ========================================================
    // FIND TOKEN
    // ========================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            shop_id,
            token_hash,
            expires_at,
            used_at,
            created_at
        FROM student_registration_tokens
        WHERE token_hash = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $token_hash
    ]);

    $registration = $stmt->fetch();


    // ========================================================
    // TOKEN NOT FOUND
    // ========================================================

    if (!$registration) {

        $pdo->rollBack();

        response(
            false,
            'Invalid registration token.',
            [],
            401
        );
    }


    // ========================================================
    // TOKEN USED CHECK
    // ========================================================

    if ($registration['used_at'] !== null) {

        $pdo->rollBack();

        response(
            false,
            'Registration token has already been used.',
            [],
            409
        );
    }


    // ========================================================
    // TOKEN EXPIRY CHECK
    // ========================================================

    if (
        strtotime($registration['expires_at']) === false ||
        strtotime($registration['expires_at']) <= time()
    ) {

        $pdo->rollBack();

        response(
            false,
            'Registration token has expired. Please scan the QR code again.',
            [],
            401
        );
    }


    // ========================================================
    // FIND SHOP FROM TOKEN
    // ========================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            shop_name,
            status
        FROM shops
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $registration['shop_id']
    ]);

    $shop = $stmt->fetch();


    // ========================================================
    // SHOP NOT FOUND
    // ========================================================

    if (!$shop) {

        $pdo->rollBack();

        response(
            false,
            'Shop associated with this registration token was not found.',
            [],
            404
        );
    }


    // ========================================================
    // SHOP STATUS CHECK
    // ========================================================

    if ($shop['status'] !== 'active') {

        $pdo->rollBack();

        response(
            false,
            'This shop is currently inactive.',
            [],
            403
        );
    }


    // ========================================================
    // DUPLICATE PHONE CHECK
    // ========================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            phone,
            email
        FROM students
        WHERE phone = ?
        LIMIT 1
    ");

    $stmt->execute([
        $phone
    ]);

    $existing_phone = $stmt->fetch();


    if ($existing_phone) {

        $pdo->rollBack();

        response(
            false,
            'A student with this mobile number is already registered.',
            [
                'duplicate_field' => 'phone',
                'student_id'      => (int) $existing_phone['id']
            ],
            409
        );
    }


    // ========================================================
    // DUPLICATE EMAIL CHECK
    // ========================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            phone,
            email
        FROM students
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([
        $email
    ]);

    $existing_email = $stmt->fetch();


    if ($existing_email) {

        $pdo->rollBack();

        response(
            false,
            'A student with this email address is already registered.',
            [
                'duplicate_field' => 'email',
                'student_id'      => (int) $existing_email['id']
            ],
            409
        );
    }


    // ========================================================
    // EXACT DUPLICATE STUDENT CHECK
    // ========================================================
    //
    // Prevent completely identical student records.
    //
    // We intentionally DO NOT make name, gender, location,
    // qualification, institution, course or training mode
    // individually unique because multiple students can
    // legitimately have the same values.
    //

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            phone,
            email,
            current_location,
            highest_qualification,
            institution_name,
            current_status,
            current_status_other,
            course_applied,
            training_mode
        FROM students
        WHERE
            LOWER(TRIM(name)) = LOWER(TRIM(?))
            AND LOWER(TRIM(gender)) = LOWER(TRIM(?))
            AND phone = ?
            AND LOWER(TRIM(email)) = LOWER(TRIM(?))
            AND LOWER(TRIM(current_location)) = LOWER(TRIM(?))
            AND LOWER(TRIM(highest_qualification)) = LOWER(TRIM(?))
            AND (
                (institution_name IS NULL AND ? IS NULL)
                OR
                LOWER(TRIM(institution_name)) = LOWER(TRIM(?))
            )
            AND LOWER(TRIM(current_status)) = LOWER(TRIM(?))
            AND (
                (current_status_other IS NULL AND ? IS NULL)
                OR
                LOWER(TRIM(current_status_other)) = LOWER(TRIM(?))
            )
            AND LOWER(TRIM(course_applied)) = LOWER(TRIM(?))
            AND LOWER(TRIM(training_mode)) = LOWER(TRIM(?))
        LIMIT 1
    ");

    $stmt->execute([
        $name,
        $gender_normalized,
        $phone,
        $email,
        $current_location,
        $highest_qualification,

        $institution_name,
        $institution_name,

        $current_status,

        $current_status_other,
        $current_status_other,

        $course_applied,
        $training_mode
    ]);

    $existing_student = $stmt->fetch();


    if ($existing_student) {

        $pdo->rollBack();

        response(
            false,
            'This student registration already exists. Duplicate registration is not allowed.',
            [
                'duplicate_field' => 'complete_record',
                'student_id'      => (int) $existing_student['id']
            ],
            409
        );
    }


    // ========================================================
    // CREATE STUDENT
    // ========================================================

    $stmt = $pdo->prepare("
        INSERT INTO students (
            name,
            gender,
            phone,
            email,
            current_location,
            highest_qualification,
            institution_name,
            current_status,
            current_status_other,
            course_applied,
            training_mode,
            declaration
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $name,
        $gender_normalized,
        $phone,
        $email,
        $current_location,
        $highest_qualification,
        $institution_name,
        $current_status,
        $current_status_other,
        $course_applied,
        $training_mode,
        $declaration
    ]);


    // ========================================================
    // STUDENT ID
    // ========================================================

    $student_id = (int) $pdo->lastInsertId();


    if ($student_id <= 0) {

        $pdo->rollBack();

        response(
            false,
            'Student registration failed.',
            [],
            500
        );
    }


    // ========================================================
    // CREATE STUDENT LEAD
    // ========================================================

    $stmt = $pdo->prepare("
        INSERT INTO student_leads (
            shop_id,
            student_id,
            status
        )
        VALUES (?, ?, 'need_to_contact')
    ");

    $stmt->execute([
        $registration['shop_id'],
        $student_id
    ]);


    // ========================================================
    // LEAD ID
    // ========================================================

    $lead_id = (int) $pdo->lastInsertId();


    if ($lead_id <= 0) {

        $pdo->rollBack();

        response(
            false,
            'Student lead could not be created.',
            [],
            500
        );
    }


    // ========================================================
    // MARK TOKEN AS USED
    // ========================================================

    $stmt = $pdo->prepare("
        UPDATE student_registration_tokens
        SET used_at = NOW()
        WHERE id = ?
        AND used_at IS NULL
    ");

    $stmt->execute([
        $registration['id']
    ]);


    // ========================================================
    // TOKEN UPDATE VALIDATION
    // ========================================================

    if ($stmt->rowCount() !== 1) {

        $pdo->rollBack();

        response(
            false,
            'Registration token could not be completed.',
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

    response(
        false,
        'Student registration failed due to a database error.',
        [],
        500
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    response(
        false,
        'Student registration failed.',
        [],
        500
    );
}


// ============================================================
// GET CREATED STUDENT
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        gender,
        phone,
        email,
        current_location,
        highest_qualification,
        institution_name,
        current_status,
        current_status_other,
        course_applied,
        training_mode,
        declaration,
        created_at,
        updated_at
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $student_id
]);

$student = $stmt->fetch();


// ============================================================
// GET CREATED LEAD
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        shop_id,
        student_id,
        status,
        admin_remarks,
        contacted_at,
        converted_at,
        rejected_at,
        created_at,
        updated_at
    FROM student_leads
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $lead_id
]);

$lead = $stmt->fetch();


// ============================================================
// RESPONSE DATA
// ============================================================

response(
    true,
    'Student registration submitted successfully.',
    [
        'shop' => [
            'id'   => (int) $shop['id'],
            'name' => $shop['shop_name']
        ],

        'student' => [
            'id'                       => (int) $student['id'],
            'name'                     => $student['name'],
            'gender'                   => $student['gender'],
            'phone'                    => $student['phone'],
            'email'                    => $student['email'],
            'current_location'         => $student['current_location'],
            'highest_qualification'    => $student['highest_qualification'],
            'institution_name'         => $student['institution_name'],
            'current_status'           => $student['current_status'],
            'current_status_other'     => $student['current_status_other'],
            'course_applied'           => $student['course_applied'],
            'training_mode'            => $student['training_mode'],
            'declaration'              => (bool) $student['declaration'],
            'created_at'               => $student['created_at'],
            'updated_at'               => $student['updated_at']
        ],

        'lead' => [
            'id'             => (int) $lead['id'],
            'shop_id'        => (int) $lead['shop_id'],
            'student_id'     => (int) $lead['student_id'],
            'status'         => $lead['status'],
            'admin_remarks'  => $lead['admin_remarks'],
            'contacted_at'   => $lead['contacted_at'],
            'converted_at'   => $lead['converted_at'],
            'rejected_at'    => $lead['rejected_at'],
            'created_at'     => $lead['created_at'],
            'updated_at'     => $lead['updated_at']
        ]
    ],
    201
);