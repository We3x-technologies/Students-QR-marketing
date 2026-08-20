<?php

date_default_timezone_set('Asia/Kolkata');


// ============================================================
// PROJECT CONFIGURATION
// ============================================================

define('BASE_URL', 'http://localhost/we3x_qr_marketing/');

define('QR_FOLDER', __DIR__ . '/../uploads/qr/');
define('QR_URL', BASE_URL . 'uploads/qr/');

define('ADMIN_ROLE', 'admin');
define('SHOP_ROLE', 'shop');

define('STATUS_NEED_TO_CONTACT', 'need_to_contact');
define('STATUS_CONVERTED', 'converted');
define('STATUS_REJECTED', 'rejected');


// ============================================================
// CONFIGURATION VALIDATION
// ============================================================

if (!defined('BASE_URL') || empty(BASE_URL)) {
    die('BASE_URL is not configured.');
}

if (!filter_var(BASE_URL, FILTER_VALIDATE_URL)) {
    die('Invalid BASE_URL configuration.');
}

if (!defined('QR_FOLDER') || empty(QR_FOLDER)) {
    die('QR_FOLDER is not configured.');
}

if (!defined('QR_URL') || empty(QR_URL)) {
    die('QR_URL is not configured.');
}

if (!defined('ADMIN_ROLE') || empty(ADMIN_ROLE)) {
    die('ADMIN_ROLE is not configured.');
}

if (!defined('SHOP_ROLE') || empty(SHOP_ROLE)) {
    die('SHOP_ROLE is not configured.');
}

if (!defined('STATUS_NEED_TO_CONTACT') || empty(STATUS_NEED_TO_CONTACT)) {
    die('Need to Contact status is not configured.');
}

if (!defined('STATUS_CONVERTED') || empty(STATUS_CONVERTED)) {
    die('Converted status is not configured.');
}

if (!defined('STATUS_REJECTED') || empty(STATUS_REJECTED)) {
    die('Rejected status is not configured.');
}


// ============================================================
// QR DIRECTORY VALIDATION
// ============================================================

if (!is_dir(QR_FOLDER)) {

    if (!mkdir(QR_FOLDER, 0755, true)) {
        die('QR upload directory could not be created.');
    }
}

if (!is_writable(QR_FOLDER)) {
    die('QR upload directory is not writable.');
}