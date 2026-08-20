<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../aws_external_biometric.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || (empty($input['face_descriptor']) && empty($input['face_photo']))) {
    echo json_encode(['success' => false, 'message' => 'No biometric face data provided.']);
    exit();
}

$probe_descriptor = $input['face_descriptor'] ?? null;
$probe_photo_data = $input['face_photo'] ?? null;
$email_filter = !empty($input['email']) ? clean_input($input['email']) : null;

// Query active users with enrolled biometric profiles
$query_sql = "SELECT id, fullname, email, wallet_balance, face_descriptor, face_photo FROM users WHERE status='Active' AND (face_descriptor IS NOT NULL OR face_photo IS NOT NULL)";
if ($email_filter) {
    $query_sql .= " AND email='$email_filter'";
}

$result = mysqli_query($conn, $query_sql);

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode([
        'success' => false,
        'message' => $email_filter ? 'No face registered for this email.' : 'No registered biometric profiles found.'
    ]);
    exit();
}

$best_match_user = null;
$aws_match_found = false;
$aws_similarity = 0.0;

// Decode probe photo bytes if provided
$probe_bytes = null;
if (!empty($probe_photo_data)) {
    if (preg_match('/^data:image\/(\w+);base64,/', $probe_photo_data)) {
        $probe_bytes = base64_decode(substr($probe_photo_data, strpos($probe_photo_data, ',') + 1));
    } else {
        $probe_bytes = base64_decode($probe_photo_data);
    }
}

// 1. Primary Engine: AWS Rekognition
if (isAwsRekognitionAvailable() && $probe_bytes && strlen($probe_bytes) > 500) {
    $rekClient = getRekognitionClient();

    while ($row = mysqli_fetch_assoc($result)) {
        $user_id = (int)$row['id'];
        $stored_bytes = null;

        // Check local disk first
        $local_file = __DIR__ . '/../uploads/faces/user_' . $user_id . '.jpg';
        if (file_exists($local_file)) {
            $stored_bytes = file_get_contents($local_file);
        } elseif (!empty($row['face_photo'])) {
            $sp = $row['face_photo'];
            if (preg_match('/^data:image\/(\w+);base64,/', $sp)) {
                $stored_bytes = base64_decode(substr($sp, strpos($sp, ',') + 1));
            } else {
                $stored_bytes = base64_decode($sp);
            }
        }

        if ($stored_bytes && strlen($stored_bytes) > 500) {
            $cmp = compareFacesData($rekClient, $probe_bytes, $stored_bytes, 75);
            if (!empty($cmp['match']) && $cmp['match'] === true) {
                $best_match_user = $row;
                $aws_match_found = true;
                $aws_similarity = (float)$cmp['similarity'];
                break;
            }
        }
    }
}

// 2. Secondary Engine: Local 128-D Euclidean Vector Fallback
if (!$aws_match_found && is_array($probe_descriptor)) {
    mysqli_data_seek($result, 0); // Reset pointer
    $min_distance = 999.0;
    $MATCH_THRESHOLD = 0.52;

    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($row['face_descriptor'])) continue;
        $stored_descriptor = json_decode($row['face_descriptor'], true);
        if (!is_array($stored_descriptor)) continue;

        $dist = compute_euclidean_distance($probe_descriptor, $stored_descriptor);
        if ($dist < $min_distance) {
            $min_distance = $dist;
            $candidate = $row;
        }
    }

    if (isset($candidate) && $min_distance <= $MATCH_THRESHOLD) {
        $best_match_user = $candidate;
        $aws_similarity = round((1 - ($min_distance / $MATCH_THRESHOLD)) * 100, 1);
    }
}

if ($best_match_user) {
    // Biometric Match Confirmed! Log user in
    $_SESSION['user_id'] = $best_match_user['id'];
    $_SESSION['fullname'] = $best_match_user['fullname'];
    $_SESSION['auth_method'] = 'biometric_face';

    // Dispatch Login Security Email & In-App Notification
    try {
        require_once(__DIR__ . "/../includes/NotificationService.php");
        NotificationService::send_login_acknowledgement((int)$best_match_user['id'], $best_match_user['email'], $best_match_user['fullname'], 'biometric_face');
    } catch (Throwable $e) {}

    echo json_encode([
        'success' => true,
        'message' => $aws_match_found ? 'AWS Rekognition facial verification successful!' : 'Biometric authentication successful!',
        'engine' => $aws_match_found ? 'AWS Rekognition' : 'Neural Vector Engine',
        'confidence' => $aws_similarity,
        'user' => [
            'id' => $best_match_user['id'],
            'fullname' => $best_match_user['fullname'],
            'email' => $best_match_user['email']
        ],
        'redirect' => '../user/dashboard.php'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Face not recognized. Please align your face clearly in good lighting or use password login.'
    ]);
}
?>
