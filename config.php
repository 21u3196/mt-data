<?php

/* ==========================
   ENVIRONMENT & CONFIG LOADER
========================== */

// Parse .env file if present
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($envKey, $envVal) = explode('=', $line, 2);
            $envKey = trim($envKey);
            $envVal = trim($envVal, " \t\n\r\0\x0B\"'");
            if (getenv($envKey) === false) {
                putenv("{$envKey}={$envVal}");
                $_ENV[$envKey] = $envVal;
                $_SERVER[$envKey] = $envVal;
            }
        }
    }
}

/* ==========================
   DATABASE CONFIGURATION
========================== */

$db_host = "localhost";
$db_user = "root";
$db_pass = "rootpassword";
$db_name = "datavending";
$db_port = 3306;
$db_ssl  = false;

// Check for Database URL / URI format (e.g. Aiven, Render, ClearDB)
$dbUri = getenv('DB_URI') ?: (getenv('DATABASE_URL') ?: (getenv('MYSQL_URL') ?: getenv('CLEARDB_DATABASE_URL')));
if (!empty($dbUri)) {
    $parsed = parse_url($dbUri);
    if ($parsed) {
        if (!empty($parsed['host'])) $db_host = $parsed['host'];
        if (!empty($parsed['user'])) $db_user = urldecode($parsed['user']);
        if (isset($parsed['pass']))  $db_pass = urldecode($parsed['pass']);
        if (!empty($parsed['port'])) $db_port = (int)$parsed['port'];
        if (!empty($parsed['path'])) $db_name = trim($parsed['path'], '/');
        if (!empty($parsed['query']) && (stripos($parsed['query'], 'ssl') !== false)) {
            $db_ssl = true;
        }
    }
} else {
    if (getenv('DB_HOST'))     $db_host = getenv('DB_HOST');
    if (getenv('DB_USER'))     $db_user = getenv('DB_USER');
    if (getenv('DB_PASS') !== false)     $db_pass = getenv('DB_PASS');
    elseif (getenv('DB_PASSWORD') !== false) $db_pass = getenv('DB_PASSWORD');
    if (getenv('DB_NAME'))     $db_name = getenv('DB_NAME');
    if (getenv('DB_PORT'))     $db_port = (int)getenv('DB_PORT');
}

// Enable SSL if using cloud provider or explicit flag
if (stripos($db_host, 'aivencloud.com') !== false || $db_port === 25229 || getenv('DB_SSL') === 'true') {
    $db_ssl = true;
}

/* ==========================
   DATABASE CONNECTION
========================== */

// Disable default fatal exceptions in PHP 8.1+ so we can handle fallbacks and errors gracefully
mysqli_report(MYSQLI_REPORT_OFF);

$conn = false;
$last_db_error = '';

// Helper function to safely connect
function attempt_db_connect($host, $user, $pass, $name, $port, $ssl = false) {
    try {
        if ($ssl) {
            $c = mysqli_init();
            if ($c) {
                mysqli_ssl_set($c, NULL, NULL, NULL, NULL, NULL);
                mysqli_options($c, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
                if (@mysqli_real_connect($c, $host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL)) {
                    return $c;
                }
            }
        }
        $c = mysqli_init();
        if ($c) {
            mysqli_options($c, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            if (@mysqli_real_connect($c, $host, $user, $pass, $name, $port)) {
                return $c;
            }
        }
    } catch (Throwable $e) {
        // Suppress and record error
    }
    return false;
}

// Attempt 1: Configured credentials
if (!empty($db_host)) {
    $conn = attempt_db_connect($db_host, $db_user, $db_pass, $db_name, $db_port, $db_ssl);
    if (!$conn) {
        $last_db_error = mysqli_connect_error() ?: "Could not connect to {$db_host}:{$db_port}";
    }
}

// Attempt 2: Local fallback attempt (trying configured DB name & local datavending DB)
if (!$conn) {
    $fallback_dbs = array_unique(array_filter([$db_name, "datavending"]));
    foreach ($fallback_dbs as $fb_db) {
        // Fallback A: Local Docker / MariaDB with configured rootpassword
        $conn = attempt_db_connect("localhost", "root", "rootpassword", $fb_db, 3306, false);
        if ($conn) break;

        // Fallback B: 127.0.0.1 with rootpassword
        $conn = attempt_db_connect("127.0.0.1", "root", "rootpassword", $fb_db, 3306, false);
        if ($conn) break;
    }
}

// Handle Connection Failure Gracefully with informative UI
if (!$conn) {
    http_response_code(500);
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection - MT Data</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-zinc-100 flex items-center justify-center min-h-screen p-4 font-sans antialiased text-zinc-900">
        <div class="max-w-md w-full bg-white rounded-3xl p-8 shadow-sm border border-zinc-200 text-center">
            <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto text-2xl mb-4">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h1 class="text-xl font-extrabold text-zinc-900 mb-2">Connecting to Database...</h1>
            <p class="text-xs sm:text-sm text-zinc-500 mb-5 leading-relaxed">
                Could not connect to the database service at <span class="font-mono text-zinc-800 font-bold">' . htmlspecialchars($db_host) . '</span>.
            </p>
            ' . ($last_db_error ? '<div class="p-3 bg-red-50 border border-red-200 rounded-xl text-left text-xs font-mono text-red-700 mb-5 break-all">' . htmlspecialchars($last_db_error) . '</div>' : '') . '
            <div class="space-y-2">
                <a href="javascript:location.reload()" class="inline-flex items-center justify-center w-full py-3 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm transition-all shadow-xs">
                    Retry Connection
                </a>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

mysqli_set_charset($conn, "utf8mb4");

// Auto-Bootstrap Schema if tables are missing (e.g. fresh Aiven / cloud database)
try {
    $check_users = @mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if (!$check_users || mysqli_num_rows($check_users) === 0) {
        $schema_file = __DIR__ . '/database/datavending.sql';
        if (file_exists($schema_file)) {
            $sql_raw = file_get_contents($schema_file);
            $lines = explode("\n", $sql_raw);
            $clean_buffer = "";
            foreach ($lines as $l) {
                $trimmed = trim($l);
                if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
                    continue;
                }
                $clean_buffer .= $l . "\n";
            }
            $queries = explode(";", $clean_buffer);
            foreach ($queries as $q) {
                $q = trim($q);
                if (!empty($q)) {
                    @mysqli_query($conn, $q);
                }
            }
        }
    }

    // Ensure notifications table exists
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `service_type` varchar(50) DEFAULT 'System',
        `channels` varchar(100) DEFAULT 'in_app,email',
        `metadata` longtext DEFAULT NULL,
        `is_read` tinyint(1) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // Ignore migration warnings
}

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
    if (!$conn) {
        return trim(htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8'));
    }
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
    if (!is_logged_in() || !$conn) {
        return null;
    }
    $user_id = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, fullname, email, phone, wallet_balance, status, face_descriptor, face_photo, face_enrolled_at, created_at FROM users WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = $result ? mysqli_fetch_assoc($result) : null;
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
    if (!$conn) return null;
    $id = (int)$user_id;
    $query = @mysqli_query($conn, "SELECT * FROM users WHERE id='$id'");
    return ($query && $row = mysqli_fetch_assoc($query)) ? $row : null;
}

/**
 * Get admin by ID
 */
function get_admin($admin_id) {
    global $conn;
    if (!$conn) return null;
    $id = (int)$admin_id;
    $query = @mysqli_query($conn, "SELECT * FROM admins WHERE id='$id'");
    return ($query && $row = mysqli_fetch_assoc($query)) ? $row : null;
}

/**
 * Get current wallet balance
 */
function get_wallet_balance($user_id) {
    global $conn;
    if (!$conn) return 0.00;
    $id = (int)$user_id;
    $query = @mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id='$id'");
    return ($query && $row = mysqli_fetch_assoc($query)) ? (float)$row['wallet_balance'] : 0.00;
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
    if (!$conn) return 0;
    $res = @mysqli_query($conn, "SELECT COUNT(*) as c FROM users");
    return ($res && $row = mysqli_fetch_assoc($res)) ? (int)$row['c'] : 0;
}

function total_transactions() {
    global $conn;
    if (!$conn) return 0;
    $res = @mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions");
    return ($res && $row = mysqli_fetch_assoc($res)) ? (int)$row['c'] : 0;
}

function total_plans() {
    global $conn;
    if (!$conn) return 0;
    $res = @mysqli_query($conn, "SELECT COUNT(*) as c FROM data_plans");
    return ($res && $row = mysqli_fetch_assoc($res)) ? (int)$row['c'] : 0;
}

?>