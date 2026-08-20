<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../aws_external_biometric.php");

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

if (empty($input['face_photo'])) {
    echo json_encode(['success' => false, 'message' => 'A clear face photo is required for enrollment. Please position your face in the camera frame.']);
    exit();
}

$photo_data = $input['face_photo'];
$raw_bytes = null;

if (preg_match('/^data:image\/(\w+);base64,/', $photo_data, $type)) {
    $photo_base64 = substr($photo_data, strpos($photo_data, ',') + 1);
    $raw_bytes = base64_decode($photo_base64);
} else {
    $raw_bytes = base64_decode($photo_data);
}

if (!$raw_bytes || strlen($raw_bytes) < 1000) {
    echo json_encode(['success' => false, 'message' => 'Invalid face photo captured. Please ensure good lighting and try again.']);
    exit();
}

// AWS Rekognition Face Quality Standard Check
if (isAwsRekognitionAvailable()) {
    $rekClient = getRekognitionClient();
    $awsQuality = detectFaceQuality($rekClient, $raw_bytes);
    if (isset($awsQuality['valid']) && $awsQuality['valid'] === false) {
        echo json_encode([
            'success' => false,
            'message' => 'AWS Rekognition Quality Check Failed: ' . ($awsQuality['error'] ?? 'Unsuitable facial image quality.')
        ]);
        exit();
    }
}

$user_id = (int)$_SESSION['user_id'];

// Save photo locally to uploads/faces/
$upload_dir = __DIR__ . '/../uploads/faces';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}
$file_path = $upload_dir . '/user_' . $user_id . '.jpg';
@file_put_contents($file_path, $raw_bytes);

$descriptor_json = json_encode($input['face_descriptor']);
$photo_saved_value = clean_input($photo_data);

$stmt = mysqli_prepare($conn, "UPDATE users SET face_descriptor = ?, face_photo = ?, face_enrolled_at = NOW() WHERE id = ?");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ssi", $descriptor_json, $photo_saved_value, $user_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        echo json_encode([
            'success' => true,
            'message' => 'Face biometrics enrolled successfully using AWS Rekognition standards!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error while saving biometrics.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare database statement.']);
}
?>
