<?php
/**
 * Face Authentication API
 *
 * Authenticates users via Face ID with active blink liveness verification.
 * Primary engine: AWS Rekognition (compareFaces)
 * Fallback engine: Local 128-D Euclidean vector matching
 *
 * Request (JSON):
 *   face_photo:        base64 JPEG   (required for AWS matching)
 *   face_descriptor:   128-D float[] (optional, used for local vector fallback)
 *   email:             string        (optional, narrows search to one account)
 *   liveness_verified: bool          (true = client blink liveness passed)
 */
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../aws_external_biometric.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || (empty($input['face_descriptor']) && empty($input['face_photo']))) {
    echo json_encode(['success' => false, 'message' => 'No face data provided. Please allow camera access and position your face in frame.']);
    exit();
}

$probe_descriptor   = $input['face_descriptor'] ?? null;
$probe_photo        = $input['face_photo']       ?? null;
$email_filter       = !empty($input['email']) ? trim($input['email']) : null;
$liveness_verified  = !empty($input['liveness_verified']) && $input['liveness_verified'] === true;

// Decode probe photo bytes
$probe_bytes = null;
if (!empty($probe_photo)) {
    $probe_bytes = preg_match('/^data:image\/\w+;base64,/', $probe_photo)
        ? base64_decode(substr($probe_photo, strpos($probe_photo, ',') + 1))
        : base64_decode($probe_photo);
}

// ── 1. Build candidate query ──────────────────────────────────────────────────
$query = "SELECT id, fullname, email, wallet_balance, face_descriptor, face_photo
          FROM users
          WHERE status='Active'
            AND (face_enrolled_at IS NOT NULL OR face_descriptor IS NOT NULL OR face_photo IS NOT NULL)";

if ($email_filter) {
    $stmt = mysqli_prepare($conn, $query . ' AND email = ?');
    mysqli_stmt_bind_param($stmt, 's', $email_filter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode([
        'success' => false,
        'message' => $email_filter
            ? 'No Face ID enrolled for ' . htmlspecialchars($email_filter) . '. Please sign in with password first to enroll.'
            : 'No enrolled biometric profiles found in the system.',
    ]);
    exit();
}

// ── 2. Optional: AWS face quality pre-check ───────────────────────────────────
// For auth we DON'T enforce requireEyesOpen because the candidate photo is
// captured BEFORE the blink (eyes open), and we just want basic quality.
$rekClient = isAwsRekognitionAvailable() ? getRekognitionClient() : null;

if ($rekClient && $probe_bytes && strlen($probe_bytes) > 500) {
    $quality = detectFaceQuality($rekClient, $probe_bytes, false /* no eye-open liveness gate on auth */);
    if (isset($quality['valid']) && $quality['valid'] === false) {
        // Soft fail: only block on hard errors (no face / multiple faces)
        $hard_codes = ['NO_FACE', 'MULTIPLE_FACES'];
        if (in_array($quality['code'] ?? '', $hard_codes, true)) {
            echo json_encode([
                'success' => false,
                'message' => $quality['error'] ?? 'Face not detected. Please reposition your face in good lighting.',
                'code'    => $quality['code'],
            ]);
            exit();
        }
        // For other quality issues, proceed with matching (don't block auth)
    }
}

$matched_user      = null;
$match_confidence  = 0.0;
$engine_used       = null;

// ── 3. AWS Rekognition Comparison (Primary) ───────────────────────────────────
if ($rekClient && $probe_bytes && strlen($probe_bytes) > 500) {
    while ($row = mysqli_fetch_assoc($result)) {
        $candidate_id = (int)$row['id'];
        $stored_bytes = null;

        $local_file = __DIR__ . '/../uploads/faces/user_' . $candidate_id . '.jpg';
        if (file_exists($local_file)) {
            $stored_bytes = file_get_contents($local_file);
        } elseif (!empty($row['face_photo'])) {
            $sp = $row['face_photo'];
            $stored_bytes = preg_match('/^data:image\/\w+;base64,/', $sp)
                ? base64_decode(substr($sp, strpos($sp, ',') + 1))
                : base64_decode($sp);
        }

        if ($stored_bytes && strlen($stored_bytes) > 500) {
            $cmp = compareFacesData($rekClient, $probe_bytes, $stored_bytes, 75);
            if (!empty($cmp['match']) && $cmp['match'] === true) {
                $matched_user     = $row;
                $match_confidence = (float)$cmp['similarity'];
                $engine_used      = 'AWS Rekognition';
                break;
            }
        }
    }
}

// ── 4. Local 128-D Euclidean Vector Matching (Fallback) ──────────────────────
if (!$matched_user && is_array($probe_descriptor) && count($probe_descriptor) > 0) {
    if (mysqli_num_rows($result) > 0) {
        mysqli_data_seek($result, 0);
    }

    $min_dist       = 999.0;
    $best_candidate = null;

    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($row['face_descriptor'])) continue;
        $stored_descriptor = json_decode($row['face_descriptor'], true);
        if (!is_array($stored_descriptor)) continue;

        $dist = compute_euclidean_distance($probe_descriptor, $stored_descriptor);
        if ($dist < $min_dist) {
            $min_dist       = $dist;
            $best_candidate = $row;
        }
    }

    if ($best_candidate && $min_dist <= 0.52) {
        $matched_user     = $best_candidate;
        $match_confidence = round((1 - ($min_dist / 0.52)) * 100, 1);
        $engine_used      = 'Local Vector Engine';
    }
}

// ── 5. Handle Result & Establish Session ─────────────────────────────────────
if ($matched_user) {
    $_SESSION['user_id']    = $matched_user['id'];
    $_SESSION['fullname']   = $matched_user['fullname'];
    $_SESSION['auth_method'] = 'biometric_face';

    try {
        require_once(__DIR__ . '/../includes/NotificationService.php');
        NotificationService::send_login_acknowledgement(
            (int)$matched_user['id'],
            $matched_user['email'],
            $matched_user['fullname'],
            'biometric_face'
        );
    } catch (Throwable $e) {}

    echo json_encode([
        'success'    => true,
        'message'    => $engine_used === 'AWS Rekognition'
            ? 'Identity verified with AWS Rekognition! Redirecting...'
            : 'Face ID verified! Redirecting...',
        'engine'     => $engine_used,
        'confidence' => $match_confidence,
        'user'       => [
            'id'       => $matched_user['id'],
            'fullname' => $matched_user['fullname'],
            'email'    => $matched_user['email'],
        ],
        'redirect'   => '../user/dashboard.php',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $email_filter
            ? 'Face scan does not match the enrolled face for ' . htmlspecialchars($email_filter) . '. Try again or use your password.'
            : 'Face not recognized. Ensure good lighting and look directly at the camera, or sign in with password.',
    ]);
}
?>
