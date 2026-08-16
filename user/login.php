<?php
include_once("../config.php");

if (is_logged_in()) {
    redirect("dashboard.php");
}

$message = "";

if (isset($_POST['login'])) {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT id, fullname, email, password, wallet_balance, status FROM users WHERE email = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($res)) {
            if ($user['status'] !== 'Active') {
                $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-circle-exclamation text-lg'></i> Your account is inactive. Please contact support.</div>";
            } elseif (verify_user_password($password, $user['password'], $user['id'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['auth_method'] = 'password';
                
                redirect("dashboard.php");
            } else {
                $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Incorrect password entered.</div>";
            }
        } else {
            $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> No account found with this email.</div>";
        }
        mysqli_stmt_close($stmt);
    }
}

$page_title = "User Login";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative">
    
    <!-- Background Decor -->
    <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-brand-500/10 rounded-full blur-3xl pointer-events-none -z-10"></div>

    <div class="w-full max-w-md bg-white/95 backdrop-blur-xl rounded-3xl p-8 sm:p-10 shadow-2xl ring-1 ring-slate-900/5 border border-slate-100">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-600 to-accent-500 text-white flex items-center justify-center mx-auto text-xl shadow-lg shadow-brand-500/25 mb-4">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Welcome Back</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Sign in with password or 1-click Face ID</p>
        </div>

        <?php echo $message; ?>

        <!-- Tab Switcher -->
        <div class="p-1 bg-slate-100 rounded-2xl flex gap-1 mb-6">
            <button type="button" id="tabPasswordBtn" onclick="switchAuthTab('password')" class="flex-1 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all bg-white text-slate-900 shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-key text-brand-500"></i> Password
            </button>
            <button type="button" id="tabFaceBtn" onclick="switchAuthTab('face')" class="flex-1 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2">
                <i class="fa-solid fa-face-viewfinder text-accent-500"></i> Face ID
            </button>
        </div>

        <!-- Password Auth Form -->
        <div id="passwordAuthPane">
            <form method="POST" autocomplete="on" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5" for="loginEmail">
                        Email Address
                    </label>
                    <div class="relative rounded-xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-500 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" id="loginEmail" name="email" class="w-full pl-10 pr-4 py-3 rounded-xl text-sm text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="name@example.com" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5" for="loginPassword">
                        Password
                    </label>
                    <div class="relative rounded-xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-500 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" id="loginPassword" name="password" class="w-full pl-10 pr-11 py-3 rounded-xl text-sm text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="••••••••" required>
                        <button type="button" onclick="togglePasswordVisibility('loginPassword', this)" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                            <i class="fa-regular fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" class="w-full mt-2 py-3.5 px-4 rounded-xl bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-500 hover:to-accent-500 text-white font-bold text-sm shadow-lg shadow-brand-500/25 hover:shadow-glow-brand transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Account
                </button>
            </form>
        </div>

        <!-- Face Biometric Auth Pane -->
        <div id="faceAuthPane" style="display: none;" class="space-y-4 text-center">
            <div class="relative w-56 h-56 mx-auto rounded-3xl overflow-hidden border-2 border-dashed border-brand-300 bg-slate-950 flex items-center justify-center shadow-inner group">
                <video id="faceVideo" class="w-full h-full object-cover" autoplay playsinline muted></video>
                
                <!-- Reticle Overlay -->
                <div id="scanReticle" class="absolute inset-4 rounded-2xl border border-brand-400/40 pointer-events-none transition-all"></div>
                
                <!-- Laser Sweep Line -->
                <div class="absolute left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-accent-400 to-transparent shadow-[0_0_8px_#ec4899] animate-laser pointer-events-none"></div>
            </div>

            <div id="scanStatus" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-brand-50 text-brand-700 border border-brand-200">
                <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
                <span>Starting camera...</span>
            </div>

            <div>
                <div class="relative rounded-xl border border-slate-200 bg-white mb-3 text-left">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                        <i class="fa-solid fa-at"></i>
                    </span>
                    <input type="email" id="biometricTargetEmail" class="w-full pl-10 pr-4 py-2.5 rounded-xl text-xs sm:text-sm text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="Optional: Target email for instant match">
                </div>

                <button type="button" onclick="triggerFaceScan()" class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-md shadow-emerald-500/20 hover:shadow-glow-emerald transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-camera"></i> Scan & Verify Face
                </button>
            </div>
        </div>

        <!-- Footer Link -->
        <div class="mt-8 pt-6 border-t border-slate-100 text-center text-xs sm:text-sm text-slate-500">
            Don't have an account? 
            <a href="register.php" class="font-bold text-brand-600 hover:text-brand-700 ml-1">Create Account</a>
        </div>

    </div>
</div>

<script>
function switchAuthTab(mode) {
    const tabPasswordBtn = document.getElementById('tabPasswordBtn');
    const tabFaceBtn = document.getElementById('tabFaceBtn');
    const passwordPane = document.getElementById('passwordAuthPane');
    const facePane = document.getElementById('faceAuthPane');

    if (mode === 'face') {
        tabPasswordBtn.className = "flex-1 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2";
        tabFaceBtn.className = "flex-1 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all bg-white text-slate-900 shadow-sm flex items-center justify-center gap-2";
        passwordPane.style.display = 'none';
        facePane.style.display = 'block';
        initFaceLogin();
    } else {
        tabFaceBtn.className = "flex-1 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2";
        tabPasswordBtn.className = "flex-1 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all bg-white text-slate-900 shadow-sm flex items-center justify-center gap-2";
        facePane.style.display = 'none';
        passwordPane.style.display = 'block';
        window.biometricEngine.stopCamera();
    }
}

async function initFaceLogin() {
    const video = document.getElementById('faceVideo');
    const status = document.getElementById('scanStatus');
    const reticle = document.getElementById('scanReticle');

    const ok = await window.biometricEngine.startCamera(video, status, reticle);
    if (ok) {
        triggerFaceScan();
    }
}

function triggerFaceScan() {
    const emailFilter = document.getElementById('biometricTargetEmail').value.trim() || null;
    window.biometricEngine.startLoginScan(function(result) {
        window.location.href = 'dashboard.php';
    }, emailFilter);
}

function togglePasswordVisibility(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'fa-regular fa-eye-slash text-sm';
    } else {
        field.type = 'password';
        icon.className = 'fa-regular fa-eye text-sm';
    }
}
</script>

<?php include_once("../includes/footer.php"); ?>