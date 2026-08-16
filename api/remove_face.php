<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../config.php");

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$stmt = mysqli_prepare($conn, "UPDATE users SET face_descriptor = NULL, face_photo = NULL, face_enrolled_at = NULL WHERE id = ?");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'message' => 'Face biometric profile removed successfully.'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
}
?>
