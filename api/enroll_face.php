<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../config.php");

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in first.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['face_descriptor']) || !is_array($input['face_descriptor'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid biometric face data.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$descriptor_json = json_encode($input['face_descriptor']);
$photo_data = !empty($input['face_photo']) ? clean_input($input['face_photo']) : null;

$stmt = mysqli_prepare($conn, "UPDATE users SET face_descriptor = ?, face_photo = ?, face_enrolled_at = NOW() WHERE id = ?");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ssi", $descriptor_json, $photo_data, $user_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        echo json_encode([
            'success' => true,
            'message' => 'Face biometrics enrolled successfully! You can now log in using Face ID.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error while saving biometrics.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare database statement.']);
}
?>
