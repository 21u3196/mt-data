<?php
// verify.php - receives selfie + reference photo via form upload

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refPhoto = $_FILES['reference_photo']['tmp_name'];
    $selfie = $_FILES['selfie']['tmp_name'];

    $result = compareFaces($client, $refPhoto, $selfie);

    if (isset($result['error'])) {
        echo json_encode(['status' => 'error', 'message' => $result['error']]);
    } elseif ($result['match'] && $result['similarity'] >= 90) {
        echo json_encode(['status' => 'verified', 'confidence' => $result['similarity']]);
    } else {
        echo json_encode(['status' => 'rejected', 'confidence' => $result['similarity']]);
    }
}