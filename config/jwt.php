<?php

$jwt_secret = 'WE3X_QR_MARKETING_SECRET_KEY_CHANGE_THIS';

$jwt_expiry = 86400; // 24 hours

function generateToken($user_id, $username, $role)
{
    global $jwt_secret, $jwt_expiry;

    $issued_at = time();
    $expires_at = $issued_at + $jwt_expiry;

    $payload = [
        'user_id'  => (int) $user_id,
        'username' => $username,
        'role'     => $role,
        'iat'      => $issued_at,
        'exp'      => $expires_at
    ];

    $payload = base64url_encode(
        json_encode($payload)
    );

    $signature = hash_hmac(
        'sha256',
        $payload,
        $jwt_secret,
        true
    );

    $signature = base64url_encode($signature);

    return $payload . '.' . $signature;
}


function verifyToken($token)
{
    global $jwt_secret;

    if (empty($token)) {
        return false;
    }

    $parts = explode('.', $token);

    if (count($parts) !== 2) {
        return false;
    }

    $payload = $parts[0];
    $signature = $parts[1];

    $expected_signature = base64url_encode(
        hash_hmac(
            'sha256',
            $payload,
            $jwt_secret,
            true
        )
    );

    if (!hash_equals($expected_signature, $signature)) {
        return false;
    }

    $decoded = base64url_decode($payload);

    if ($decoded === false) {
        return false;
    }

    $data = json_decode($decoded, true);

    if (!is_array($data)) {
        return false;
    }

    if (!isset(
        $data['user_id'],
        $data['username'],
        $data['role'],
        $data['iat'],
        $data['exp']
    )) {
        return false;
    }

    if (time() >= $data['exp']) {
        return false;
    }

    return $data;
}


function base64url_encode($data)
{
    return rtrim(
        strtr(
            base64_encode($data),
            '+/',
            '-_'
        ),
        '='
    );
}


function base64url_decode($data)
{
    $padding = strlen($data) % 4;

    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(
        strtr(
            $data,
            '-_',
            '+/'
        ),
        true
    );
}