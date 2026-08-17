<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../config.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['face_descriptor']) || !is_array($input['face_descriptor'])) {
    echo json_encode(['success' => false, 'message' => 'No biometric face data provided.']);
    exit();
}

$probe_descriptor = $input['face_descriptor'];
$email_filter = !empty($input['email']) ? clean_input($input['email']) : null;

// Query users who have enrolled face descriptors
$query_sql = "SELECT id, fullname, email, wallet_balance, face_descriptor FROM users WHERE face_descriptor IS NOT NULL AND status='Active'";
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
$min_distance = 999.0;
$MATCH_THRESHOLD = 0.52; // Standard Euclidean distance threshold for 128-D face embeddings

while ($row = mysqli_fetch_assoc($result)) {
    $stored_descriptor = json_decode($row['face_descriptor'], true);
    if (!is_array($stored_descriptor)) {
        continue;
    }

    $dist = compute_euclidean_distance($probe_descriptor, $stored_descriptor);
    if ($dist < $min_distance) {
        $min_distance = $dist;
        $best_match_user = $row;
    }
}

if ($best_match_user && $min_distance <= $MATCH_THRESHOLD) {
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
        'message' => 'Biometric authentication successful!',
        'confidence' => round((1 - ($min_distance / $MATCH_THRESHOLD)) * 100, 1),
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
        'min_distance' => round($min_distance, 3),
        'message' => 'Face not recognized. Please align your face clearly or use password login.'
    ]);
}
?>
