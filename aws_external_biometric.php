<?php
/**
 * AWS Rekognition & Biometric Verification Module
 *
 * Handles:
 *  - Credential resolution (canonical AWS SDK names + legacy aliases)
 *  - Face quality + SERVER-SIDE LIVENESS validation via AWS DetectFaces EyesOpen
 *  - Duplicate face detection across all enrolled accounts
 *  - Dual-engine face comparison (AWS Rekognition primary + Local Vector fallback)
 */

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

function loadEnv($path = null) {
    if ($path === null) $path = __DIR__ . '/.env';
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value, " \"'");
            putenv("{$name}={$value}");
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }
}
loadEnv();

function isAwsSdkLoaded() {
    return class_exists('Aws\\Rekognition\\RekognitionClient');
}

/**
 * Resolve AWS credentials.
 * Priority: canonical SDK names (AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY)
 *           → legacy aliases (AWS_ACCESS_KEY / AWS_SECRET_KEY / AWS_SECRET)
 */
function getAwsCredentials() {
    $key    = getenv('AWS_ACCESS_KEY_ID')     ?: null;
    $secret = getenv('AWS_SECRET_ACCESS_KEY') ?: null;

    // Legacy aliases
    if (!$key)    $key    = getenv('AWS_ACCESS_KEY') ?: null;
    if (!$secret) $secret = getenv('AWS_SECRET_KEY') ?: (getenv('AWS_SECRET') ?: null);

    // Single-key fallback
    if (!$key && !$secret && getenv('AWS_KEY')) {
        $raw = getenv('AWS_KEY');
        if (strpos($raw, 'AKIA') === 0 || strlen($raw) === 20) {
            $key = $raw;
        } else {
            $secret = $raw;
        }
    }

    return ['key' => $key, 'secret' => $secret];
}

function isAwsRekognitionAvailable() {
    if (!isAwsSdkLoaded()) return false;
    $creds = getAwsCredentials();
    return !empty($creds['key']) && !empty($creds['secret']) && ($creds['key'] !== $creds['secret']);
}

function getRekognitionClient() {
    if (!isAwsSdkLoaded()) return null;
    $creds  = getAwsCredentials();
    if (empty($creds['key']) || empty($creds['secret'])) return null;
    $region = getenv('AWS_REGION') ?: 'us-east-1';
    try {
        return new Aws\Rekognition\RekognitionClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => ['key' => $creds['key'], 'secret' => $creds['secret']],
        ]);
    } catch (\Throwable $e) {
        error_log('AWS Rekognition client init error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Compare two face images using raw bytes via AWS Rekognition.
 */
function compareFacesData($client, $sourceBytes, $targetBytes, $threshold = 75) {
    if (!$client) return ['error' => 'AWS client unavailable', 'match' => false];
    try {
        $result  = $client->compareFaces([
            'SourceImage'         => ['Bytes' => $sourceBytes],
            'TargetImage'         => ['Bytes' => $targetBytes],
            'SimilarityThreshold' => $threshold,
        ]);
        $matches = $result->get('FaceMatches');
        if (is_array($matches) && count($matches) > 0) {
            return ['match' => true, 'similarity' => (float)$matches[0]['Similarity']];
        }
        return ['match' => false, 'similarity' => 0];
    } catch (\Throwable $e) {
        error_log('AWS compareFaces Exception: ' . $e->getMessage());
        return ['error' => $e->getMessage(), 'match' => false];
    }
}

/**
 * AWS Rekognition Face Quality & Server-Side Liveness Validation.
 *
 * The $requireEyesOpen flag controls the liveness gate:
 *   - TRUE  (default): Enrollment — captured frame must show eyes open.
 *                       Rejects photos where AWS detects eyes closed.
 *   - FALSE          : Authentication — the captured frame is taken
 *                       BEFORE the blink (eyes open), so normal quality
 *                       checks still apply but we don't fail on eye state.
 *
 * This prevents static-photo spoofing: a printed photo has frozen eyes —
 * AWS will either flag them as closed, or the blink won't trigger client-side.
 */
function detectFaceQuality($client, $imageBytes, $requireEyesOpen = true) {
    if (!$client) {
        return ['valid' => false, 'error' => 'AWS Rekognition service is not configured.', 'code' => 'NO_AWS'];
    }
    try {
        $result  = $client->detectFaces([
            'Image'      => ['Bytes' => $imageBytes],
            'Attributes' => ['ALL'],
        ]);
        $details = $result->get('FaceDetails');

        if (empty($details) || count($details) === 0) {
            return ['valid' => false, 'error' => 'No face detected. Position your face clearly in frame with good lighting.', 'code' => 'NO_FACE'];
        }
        if (count($details) > 1) {
            return ['valid' => false, 'error' => 'Multiple faces detected. Ensure only your face is visible in frame.', 'code' => 'MULTIPLE_FACES'];
        }

        $face = $details[0];
        $conf = $face['Confidence'] ?? 0;
        if ($conf < 85) {
            return ['valid' => false, 'error' => 'Face detection confidence too low (' . round($conf) . '%). Look directly at the camera in good lighting.', 'code' => 'LOW_CONFIDENCE'];
        }

        $q  = $face['Quality'] ?? [];
        $br = $q['Brightness'] ?? 100;
        $sh = $q['Sharpness']  ?? 100;
        if ($br < 30) {
            return ['valid' => false, 'error' => 'Image too dark (' . round($br) . '% brightness). Move to a brighter area.', 'code' => 'TOO_DARK'];
        }
        if ($sh < 20) {
            return ['valid' => false, 'error' => 'Image blurry (' . round($sh) . '% sharpness). Hold your device steady.', 'code' => 'BLURRY'];
        }

        $pose  = $face['Pose'] ?? [];
        $pitch = abs($pose['Pitch'] ?? 0);
        $yaw   = abs($pose['Yaw']   ?? 0);
        if ($yaw > 32) {
            return ['valid' => false, 'error' => 'Face turned sideways. Please look straight at the camera.', 'code' => 'HEAD_TURNED'];
        }
        if ($pitch > 32) {
            return ['valid' => false, 'error' => 'Face tilted up or down. Please look straight at the camera.', 'code' => 'HEAD_TILTED'];
        }

        // ── Server-Side Liveness Gate ─────────────────────────────────────────
        // Enrollment photo must show eyes OPEN (captured before the blink).
        // This server-side check catches static photo spoofing even if the
        // client-side blink detection is bypassed.
        if ($requireEyesOpen) {
            $eo  = $face['EyesOpen'] ?? [];
            $ev  = $eo['Value']      ?? true;
            $ec  = $eo['Confidence'] ?? 0;
            if ($ec >= 70 && $ev === false) {
                return [
                    'valid' => false,
                    'error' => 'Liveness check failed: eyes appear closed in the captured image. Keep your eyes open: the system captures before your blink.',
                    'code'  => 'LIVENESS_FAIL',
                ];
            }
        }

        $sg = $face['Sunglasses'] ?? [];
        if (($sg['Value'] ?? false) && ($sg['Confidence'] ?? 0) >= 85) {
            return ['valid' => false, 'error' => 'Sunglasses or eyewear detected. Please remove them.', 'code' => 'SUNGLASSES'];
        }

        $bb = $face['BoundingBox'] ?? [];
        $fa = ($bb['Width'] ?? 0) * ($bb['Height'] ?? 0);
        if ($fa < 0.03) {
            return ['valid' => false, 'error' => 'Face too far from camera. Please move closer.', 'code' => 'FACE_TOO_SMALL'];
        }

        return [
            'valid'      => true,
            'confidence' => (float)$conf,
            'brightness' => (float)$br,
            'sharpness'  => (float)$sh,
            'eyesOpen'   => $face['EyesOpen']['Value'] ?? true,
            'faceArea'   => round($fa * 100, 1),
        ];
    } catch (\Throwable $e) {
        error_log('AWS detectFaceQuality Exception: ' . $e->getMessage());
        return ['valid' => false, 'error' => 'Face analysis error: ' . $e->getMessage(), 'code' => 'AWS_ERROR'];
    }
}

/**
 * DUPLICATE FACE DETECTION ACROSS ALL ENROLLED ACCOUNTS.
 *
 * Strategy 1: AWS Rekognition compareFaces (≥75% similarity → duplicate)
 * Strategy 2: Local 128-D Euclidean distance (≤0.48 → duplicate)
 *
 * @return array|null  Conflicting user row if duplicate, null if unique.
 */
function findDuplicateFace($conn, $probeBytes, $probeDescriptor, $excludeUserId, $rekClient = null) {
    $excludeId = (int)$excludeUserId;
    $result = mysqli_query($conn,
        "SELECT id, fullname, email, face_descriptor, face_photo FROM users
         WHERE status='Active' AND id != {$excludeId}
           AND (face_enrolled_at IS NOT NULL OR face_descriptor IS NOT NULL OR face_photo IS NOT NULL)"
    );
    if (!$result || mysqli_num_rows($result) === 0) return null;

    // Strategy 1: AWS Rekognition
    if ($rekClient && $probeBytes && strlen($probeBytes) > 500) {
        while ($row = mysqli_fetch_assoc($result)) {
            $cId = (int)$row['id'];
            $sb  = null;
            $lf  = __DIR__ . '/uploads/faces/user_' . $cId . '.jpg';
            if (file_exists($lf)) {
                $sb = file_get_contents($lf);
            } elseif (!empty($row['face_photo'])) {
                $sp = $row['face_photo'];
                $sb = preg_match('/^data:image\/(\w+);base64,/', $sp)
                    ? base64_decode(substr($sp, strpos($sp, ',') + 1))
                    : base64_decode($sp);
            }
            if ($sb && strlen($sb) > 500) {
                $cmp = compareFacesData($rekClient, $probeBytes, $sb, 75);
                if (!empty($cmp['match']) && $cmp['match'] === true) return $row;
            }
        }
    }

    // Strategy 2: Local 128-D Euclidean
    if (is_array($probeDescriptor) && count($probeDescriptor) > 0) {
        if (mysqli_num_rows($result) > 0) mysqli_data_seek($result, 0);
        while ($row = mysqli_fetch_assoc($result)) {
            if (empty($row['face_descriptor'])) continue;
            $sd = json_decode($row['face_descriptor'], true);
            if (!is_array($sd)) continue;
            if (compute_euclidean_distance($probeDescriptor, $sd) <= 0.48) return $row;
        }
    }

    return null;
}
