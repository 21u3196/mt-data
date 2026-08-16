<?php

/* ==========================
   DATABASE CONFIGURATION
========================== */

$servername = getenv('DB_HOST') ?: "localhost";
$username   = getenv('DB_USER') ?: "root";
$password   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "rootpassword";
$database   = getenv('DB_NAME') ?: "datavending";

/* ==========================
   DATABASE CONNECTION
========================== */

$conn = @mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    // Fallback attempt with empty password if rootpassword fails
    $conn = @mysqli_connect($servername, $username, "", $database);
    if (!$conn) {
        die("Database Connection Failed: " . mysqli_connect_error());
    }
}

mysqli_set_charset($conn, "utf8mb4");

/* ==========================
   SESSION START
========================== */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/* ==========================
   PROJECT SETTINGS
========================== */

define("SITE_NAME", "MT Data");
define("SITE_TAGLINE", "Instant Data, Airtime & Cable Subscriptions");
define("TIMEZONE", "Africa/Lagos");

date_default_timezone_set(TIMEZONE);

/* ==========================
   SECURITY & AUTH HELPERS
========================== */

/**
 * Hash a plaintext password securely using Bcrypt
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against stored hash or fallback plaintext (with auto-upgrade)
 */
function verify_user_password($password, $stored_hash, $user_id = null, $is_admin = false) {
    global $conn;

    // Standard Bcrypt/Argon verify
    if (password_verify($password, $stored_hash)) {
        return true;
    }

    // Legacy plaintext fallback
    if ($password === $stored_hash) {
        // Upgrade password to hash in background
        if ($user_id !== null) {
            $new_hash = hash_password($password);
            $table = $is_admin ? "admins" : "users";
            $stmt = mysqli_prepare($conn, "UPDATE $table SET password = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "si", $new_hash, $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        return true;
    }

    return false;
}

/**
 * Sanitize string inputs
 */
function clean_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim(htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8')));
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function format_amount($amount) {
    return "₦" . number_format((float)$amount, 2);
}

/**
 * Get current logged in user details
 */
function get_current_user_data() {
    global $conn;
    if (!is_logged_in()) {
        return null;
    }
    $user_id = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, fullname, email, phone, wallet_balance, status, face_descriptor, face_photo, face_enrolled_at, created_at FROM users WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $user;
    }
    return null;
}

/**
 * Get user by ID
 */
function get_user($user_id) {
    global $conn;
    $id = (int)$user_id;
    $query = mysqli_query($conn, "SELECT * FROM users WHERE id='$id'");
    return mysqli_fetch_assoc($query);
}

/**
 * Get admin by ID
 */
function get_admin($admin_id) {
    global $conn;
    $id = (int)$admin_id;
    $query = mysqli_query($conn, "SELECT * FROM admins WHERE id='$id'");
    return mysqli_fetch_assoc($query);
}

/**
 * Get current wallet balance
 */
function get_wallet_balance($user_id) {
    global $conn;
    $id = (int)$user_id;
    $query = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id='$id'");
    $row = mysqli_fetch_assoc($query);
    return $row ? (float)$row['wallet_balance'] : 0.00;
}

/**
 * Computes Euclidean Distance between two 128-D vector arrays
 */
function compute_euclidean_distance(array $vecA, array $vecB) {
    $count = min(count($vecA), count($vecB));
    if ($count === 0) return 999.0;
    
    $sum = 0.0;
    for ($i = 0; $i < $count; $i++) {
        $diff = (float)$vecA[$i] - (float)$vecB[$i];
        $sum += $diff * $diff;
    }
    return sqrt($sum);
}

/* ==========================
   TOTAL COUNTS
========================== */

function total_users() {
    global $conn;
    $res = mysqli_query($conn, "SELECT COUNT(*) as c FROM users");
    $row = mysqli_fetch_assoc($res);
    return $row['c'];
}

function total_transactions() {
    global $conn;
    $res = mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions");
    $row = mysqli_fetch_assoc($res);
    return $row['c'];
}

function total_plans() {
    global $conn;
    $res = mysqli_query($conn, "SELECT COUNT(*) as c FROM data_plans");
    $row = mysqli_fetch_assoc($res);
    return $row['c'];
}

?>