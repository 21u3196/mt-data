<?php
/**
 * Face Enrollment API
 *
 * Pipeline:
 *  1. Auth gate (must be logged in)
 *  2. Decode + validate image bytes
 *  3. AWS Rekognition face quality + SERVER-SIDE LIVENESS check
 *  4. Duplicate face detection across all enrolled accounts
 *  5. Save face file & persist biometric profile to DB
 */
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../aws_external_biometric.php');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in first.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['face_photo'])) {
    echo json_encode(['success' => false, 'message' => 'A face photo is required. Position your face in frame and allow the blink detection to capture.']);
    exit();
}

// ── 1. Decode base64 face photo ───────────────────────────────────────────────
$photo_data = $input['face_photo'];
$raw_bytes  = null;
if (preg_match('/^data:image\/\w+;base64,/', $photo_data)) {
    $raw_bytes = base64_decode(substr($photo_data, strpos($photo_data, ',') + 1));
} else {
    $raw_bytes = base64_decode($photo_data);
}

if (!$raw_bytes || strlen($raw_bytes) < 1500) {
    echo json_encode(['success' => false, 'message' => 'Captured image is unreadable or too small. Check your camera and try again.']);
    exit();
}

$user_id    = (int)$_SESSION['user_id'];
$descriptor = (!empty($input['face_descriptor']) && is_array($input['face_descriptor'])) ? $input['face_descriptor'] : null;
// Flag sent by JS indicating a blink was detected client-side
$liveness_client = !empty($input['liveness_verified']) && $input['liveness_verified'] === true;

$rekClient  = null;
$aws_used   = false;

// ── 2. AWS Rekognition: Quality + Server-Side Liveness Gate ──────────────────
// requireEyesOpen = true: The captured frame is taken BEFORE the blink, so
// AWS should confirm eyes are open — this server-side check catches photo spoofing.
if (isAwsRekognitionAvailable()) {
    $rekClient = getRekognitionClient();
    if ($rekClient) {
        $quality = detectFaceQuality($rekClient, $raw_bytes, true);
        if (isset($quality['valid']) && $quality['valid'] === false) {
            echo json_encode([
                'success' => false,
                'message' => $quality['error'] ?? 'Face quality / liveness check failed.',
                'code'    => $quality['code']  ?? 'QUALITY_FAIL',
            ]);
            exit();
        }
        $aws_used = true;
    }
}

// ── 3. Duplicate Face Detection ───────────────────────────────────────────────
$duplicate = findDuplicateFace($conn, $raw_bytes, $descriptor, $user_id, $rekClient);
if ($duplicate) {
    echo json_encode([
        'success'             => false,
        'message'             => 'Duplicate Face Detected! This facial profile is already enrolled under another account (' . htmlspecialchars($duplicate['email']) . '). Each person may only have ONE account with Face ID.',
        'code'                => 'DUPLICATE_FACE',
        'conflicting_account' => htmlspecialchars($duplicate['email']),
    ]);
    exit();
}

// ── 4. Save face photo file ───────────────────────────────────────────────────
$upload_dir = __DIR__ . '/../uploads/faces';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}
$file_path = $upload_dir . '/user_' . $user_id . '.jpg';
file_put_contents($file_path, $raw_bytes);

// ── 5. Persist biometric profile to DB ───────────────────────────────────────
$descriptor_json = $descriptor ? json_encode($descriptor) : null;
$photo_db        = clean_input($photo_data);

$stmt = mysqli_prepare($conn, 'UPDATE users SET face_descriptor = ?, face_photo = ?, face_enrolled_at = NOW() WHERE id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ssi', $descriptor_json, $photo_db, $user_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        echo json_encode([
            'success'      => true,
            'message'      => $aws_used
                ? 'Face ID enrolled successfully! AWS Rekognition verified liveness, quality & uniqueness.'
                : 'Face ID enrolled successfully!',
            'aws_verified' => $aws_used,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error while saving biometrics. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}
?>
