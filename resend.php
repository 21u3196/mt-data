<?php
include_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/includes/NotificationService.php');

$apiKey = NotificationService::getResendApiKey();

if (!empty($apiKey)) {
    $res = NotificationService::send_resend_email(
        ['21u3196@student.mau.edu.ng'],
        'Hello from MT Data',
        '<p>Congrats on sending your <strong>first email notification</strong> from MT Data!</p>'
    );
    header('Content-Type: application/json');
    echo json_encode($res);
} else {
    echo "No RESEND_API key configured.";
}

