<?php
include_once("../config.php");

if (is_admin_logged_in()) {
    redirect("dashboard.php");
}

$message = "";

if (isset($_POST['login'])) {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT * FROM admins WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($admin = mysqli_fetch_assoc($res)) {
        if (verify_user_password($password, $admin['password'], $admin['id'], true)) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['fullname'];
            redirect("dashboard.php");
        } else {
            $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Incorrect password entered.</div>";
        }
    } else {
        $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Admin account not found.</div>";
    }
    mysqli_stmt_close($stmt);
}

$page_title = "Admin Terminal";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-zinc-50">
    <div class="w-full max-w-md bg-white rounded-2xl p-8 sm:p-10 shadow-sm border border-zinc-200">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-red-600 text-white flex items-center justify-center mx-auto text-lg shadow-sm mb-4">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="font-heading text-2xl font-bold text-zinc-900 tracking-tight">Admin Terminal</h1>
            <p class="text-xs sm:text-sm text-zinc-500 mt-1">Super administrator access control</p>
        </div>

        <?php echo $message; ?>

        <form method="POST" autocomplete="on" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5" for="adminUsername">
                    Admin Username
                </label>
                <div class="relative rounded-xl border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                        <i class="fa-solid fa-user-shield"></i>
                    </span>
                    <input type="text" id="adminUsername" name="username" class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="admin" required>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5" for="adminPassword">
                    Admin Password
                </label>
                <div class="relative rounded-xl border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" id="adminPassword" name="password" class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" name="login" class="w-full mt-2 py-3 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i> Authenticate as Admin
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center">
            <a href="../index.php" class="text-xs sm:text-sm font-semibold text-slate-500 hover:text-slate-900 transition-colors inline-flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Return to Homepage
            </a>
        </div>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>