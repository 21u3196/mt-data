<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../includes/NotificationService.php");

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$sms_logs = NotificationService::get_user_sms_logs($user_id, 20);

echo json_encode([
    'success'  => true,
    'sms_logs' => $sms_logs
]);
