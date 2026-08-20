<?php
require_once __DIR__ . '/vendor/autoload.php';

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;

// Automatically load .env file into environment variables
function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \"'");
            if (!getenv($name)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

loadEnv();

function getRekognitionClient() {
    $key = getenv('AWS_ACCESS_KEY_ID') ?: getenv('AWS_KEY');
    $secret = getenv('AWS_SECRET_ACCESS_KEY') ?: getenv('AWS_KEY');
    $region = getenv('AWS_REGION') ?: 'us-east-1';

    $config = [
        'version' => 'latest',
        'region'  => $region,
    ];

    if (!empty($key) && !empty($secret)) {
        $config['credentials'] = [
            'key'    => $key,
            'secret' => $secret,
        ];
    }

    return new RekognitionClient($config);
}

function isAwsRekognitionAvailable() {
    $key = getenv('AWS_ACCESS_KEY_ID') ?: getenv('AWS_KEY');
    $secret = getenv('AWS_SECRET_ACCESS_KEY') ?: getenv('AWS_KEY');
    return !empty($key) && !empty($secret);
}

function compareFaces($client, $sourcePath, $targetPath, $threshold = 80) {
    if ($client === null) {
        $client = getRekognitionClient();
    }

    if (!file_exists($sourcePath)) {
        return ['error' => "Source image file not found: {$sourcePath}"];
    }

    if (!file_exists($targetPath)) {
        return ['error' => "Target image file not found: {$targetPath}"];
    }

    return compareFacesData($client, file_get_contents($sourcePath), file_get_contents($targetPath), $threshold);
}

function compareFacesData($client, $sourceBytes, $targetBytes, $threshold = 75) {
    if ($client === null) {
        $client = getRekognitionClient();
    }

    try {
        $result = $client->compareFaces([
            'SourceImage' => ['Bytes' => $sourceBytes],
            'TargetImage' => ['Bytes' => $targetBytes],
            'SimilarityThreshold' => $threshold,
        ]);

        $matches = $result->get('FaceMatches');

        if (is_array($matches) && count($matches) > 0) {
            return [
                'match' => true,
                'similarity' => (float)$matches[0]['Similarity'],
            ];
        }

        return ['match' => false, 'similarity' => 0];

    } catch (AwsException $e) {
        return ['error' => $e->getAwsErrorMessage() ?: $e->getMessage()];
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Perform AWS Rekognition face detection & quality validation
 */
function detectFaceQuality($client, $imageBytes) {
    if ($client === null) {
        $client = getRekognitionClient();
    }

    try {
        $result = $client->detectFaces([
            'Image' => ['Bytes' => $imageBytes],
            'Attributes' => ['ALL']
        ]);

        $details = $result->get('FaceDetails');
        if (empty($details) || count($details) === 0) {
            return [
                'valid' => false,
                'error' => 'No clear facial features detected by AWS Rekognition. Please align your face inside the frame in good lighting.'
            ];
        }

        if (count($details) > 1) {
            return [
                'valid' => false,
                'error' => 'Multiple faces detected in image. Please ensure only your face is visible during enrollment.'
            ];
        }

        $face = $details[0];
        $confidence = $face['Confidence'] ?? 0;
        if ($confidence < 85) {
            return [
                'valid' => false,
                'error' => 'Low face detection confidence. Please position face clearly with good lighting.'
            ];
        }

        $quality = $face['Quality'] ?? [];
        $brightness = $quality['Brightness'] ?? 100;
        $sharpness = $quality['Sharpness'] ?? 100;
        $pose = $face['Pose'] ?? [];
        $pitch = abs($pose['Pitch'] ?? 0);
        $yaw = abs($pose['Yaw'] ?? 0);

        if ($brightness < 35) {
            return [
                'valid' => false,
                'error' => 'Lighting is too dark. Please enroll in a brighter environment.'
            ];
        }

        if ($sharpness < 35) {
            return [
                'valid' => false,
                'error' => 'Camera image is blurry. Please hold steady in good lighting.'
            ];
        }

        if ($pitch > 30 || $yaw > 30) {
            return [
                'valid' => false,
                'error' => 'Non-frontal face pose detected. Please look straight into the camera.'
            ];
        }

        return [
            'valid' => true,
            'confidence' => (float)$confidence,
            'brightness' => (float)$brightness,
            'sharpness' => (float)$sharpness
        ];
    } catch (AwsException $e) {
        return ['valid' => false, 'aws_error' => $e->getAwsErrorMessage() ?: $e->getMessage()];
    } catch (\Exception $e) {
        return ['valid' => false, 'error' => $e->getMessage()];
    }
}
