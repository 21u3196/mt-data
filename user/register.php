<?php
include_once("../config.php");

if (is_logged_in()) {
    redirect("dashboard.php");
}

$message = "";
$registration_success = false;

if (isset($_POST['register'])) {
    $fullname = clean_input($_POST['fullname']);
    $email    = clean_input($_POST['email']);
    $phone    = clean_input($_POST['phone']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (empty($fullname) || empty($email) || empty($phone) || empty($password)) {
        $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> All fields are required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Please provide a valid email address.</div>";
    } elseif (strlen($password) < 6) {
        $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Password must be at least 6 characters.</div>";
    } elseif ($password !== $confirm) {
        $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Passwords do not match.</div>";
    } else {
        // Check uniqueness for email and phone
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR phone = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $email, $phone);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-circle-exclamation text-lg'></i> An account with this email or phone already exists.</div>";
            } else {
                // Securely Hash Password with Bcrypt
                $password_hashed = hash_password($password);

                $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (fullname, email, phone, password, wallet_balance, status) VALUES (?, ?, ?, ?, 0.00, 'Active')");
                if ($insert_stmt) {
                    mysqli_stmt_bind_param($insert_stmt, "ssss", $fullname, $email, $phone, $password_hashed);
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $new_user_id = mysqli_insert_id($conn);
                        
                        // Automatically authenticate user
                        $_SESSION['user_id'] = $new_user_id;
                        $_SESSION['fullname'] = $fullname;
                        $_SESSION['auth_method'] = 'password';

                        // Dispatch automated welcome acknowledgement (In-App, Resend Welcome Email, and SMS Log)
                        require_once(__DIR__ . "/../includes/NotificationService.php");
                        NotificationService::send_welcome_acknowledgement($new_user_id, $email, $fullname, $phone);

                        $registration_success = true;
                    } else {
                        $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Registration failed. Please try again.</div>";
                    }
                    mysqli_stmt_close($insert_stmt);
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$page_title = "Create Account";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-zinc-50">
    <div class="w-full max-w-lg bg-white rounded-2xl p-8 sm:p-10 shadow-sm border border-zinc-200">
        
        <?php if ($registration_success): ?>
            <!-- Success / Biometric Enrollment Setup Card -->
            <div class="text-center">
                <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center mx-auto text-2xl shadow-sm mb-4">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h1 class="font-heading text-2xl font-bold text-zinc-900 tracking-tight">Account Created!</h1>
                <p class="text-xs sm:text-sm text-zinc-600 mt-1 mb-6">
                    Welcome to <?php echo SITE_NAME; ?>, <span class="font-bold text-zinc-900"><?php echo htmlspecialchars($fullname); ?></span>.
                </p>

                <div class="p-6 rounded-2xl bg-zinc-50 border border-zinc-200 text-center mb-6">
                    <div class="w-12 h-12 rounded-xl bg-zinc-900 text-white flex items-center justify-center mx-auto text-xl shadow-sm mb-3">
                        <i class="fa-solid fa-face-viewfinder"></i>
                    </div>
                    <h3 class="font-heading text-base sm:text-lg font-bold text-zinc-900 mb-1">
                        Enable 1-Click Face ID Biometrics?
                    </h3>
                    <p class="text-xs sm:text-sm text-zinc-500 mb-5 max-w-sm mx-auto">
                        Enroll your face now using your webcam to sign into your wallet without typing passwords.
                    </p>

                    <div class="space-y-3">
                        <a href="enroll_face.php" class="w-full inline-flex items-center justify-center gap-2 py-3 px-6 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-sm transition-all">
                            <i class="fa-solid fa-camera"></i> Enroll Face ID Now
                        </a>
                        <a href="dashboard.php" class="block text-xs font-semibold text-zinc-500 hover:text-zinc-900 transition-colors">
                            Skip for now, go to Dashboard <i class="fa-solid fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Registration Form -->
            <div class="text-center mb-8">
                <div class="w-12 h-12 rounded-xl bg-zinc-900 text-white flex items-center justify-center mx-auto text-lg shadow-sm mb-4">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <h1 class="font-heading text-2xl font-bold text-zinc-900 tracking-tight">Create Account</h1>
                <p class="text-xs sm:text-sm text-zinc-500 mt-1">Join thousands of users vending data effortlessly</p>
            </div>

            <?php echo $message; ?>

            <form method="POST" autocomplete="on" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5" for="regFullname">
                        Full Name
                    </label>
                    <div class="relative rounded-xl border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" id="regFullname" name="fullname" class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="e.g. Adebayo Ogunlesi" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5" for="regEmail">
                        Email Address
                    </label>
                    <div class="relative rounded-xl border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" id="regEmail" name="email" class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="name@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5" for="regPhone">
                        Phone Number
                    </label>
                    <div class="relative rounded-xl border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                            <i class="fa-solid fa-phone"></i>
                        </span>
                        <input type="tel" id="regPhone" name="phone" class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="08012345678" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5" for="regPassword">
                            Password
                        </label>
                        <div class="relative rounded-xl border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" id="regPassword" name="password" class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="Min 6 chars" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5" for="regConfirm">
                            Confirm
                        </label>
                        <div class="relative rounded-xl border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            <input type="password" id="regConfirm" name="confirm_password" class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="Repeat" required>
                        </div>
                    </div>
                </div>

                <button type="submit" name="register" class="w-full mt-2 py-3 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> Register Account
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-zinc-100 text-center text-xs sm:text-sm text-zinc-500">
                Already registered? 
                <a href="login.php" class="font-bold text-zinc-900 hover:underline ml-1">Sign In Here</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>