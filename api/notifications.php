<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../includes/NotificationService.php");

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $notifications = NotificationService::get_user_notifications($user_id, 20);
    $unread_count  = NotificationService::get_unread_count($user_id);

    echo json_encode([
        'success'      => true,
        'unread_count' => $unread_count,
        'notifications'=> $notifications
    ]);
    exit();
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? clean_input($_POST['action'] ?? '');

    if ($action === 'mark_all_read') {
        NotificationService::mark_all_as_read($user_id);
        echo json_encode(['success' => true, 'message' => 'All marked as read']);
        exit();
    }

    if ($action === 'mark_read') {
        $notif_id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
        NotificationService::mark_as_read($notif_id, $user_id);
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}
