<?php
include_once("../config.php");

if (is_logged_in()) {
    redirect("dashboard.php");
}

$message = "";

if (isset($_POST['login'])) {
    $email    = clean_input($_POST['email']);
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
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['fullname']    = $user['fullname'];
                $_SESSION['auth_method'] = 'password';
                try {
                    require_once(__DIR__ . "/../includes/NotificationService.php");
                    NotificationService::send_login_acknowledgement((int)$user['id'], $user['email'], $user['fullname'], 'password');
                } catch (Throwable $e) {}
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

<div class="flex-1 flex items-center justify-center py-6 sm:py-12 px-3.5 sm:px-6 lg:px-8 bg-zinc-50 min-h-[calc(100vh-4rem)]">
    <div class="w-full max-w-md bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-8 md:p-10 shadow-xs border border-zinc-200">

        <!-- Header -->
        <div class="text-center mb-6 sm:mb-8">
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-zinc-900 text-white flex items-center justify-center mx-auto text-base sm:text-lg shadow-xs mb-3 sm:mb-4">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="font-heading text-xl sm:text-2xl font-bold text-zinc-900 tracking-tight">Welcome Back</h1>
            <p class="text-xs sm:text-sm text-zinc-500 mt-1">Sign in with password or instant Face ID</p>
        </div>

        <?php echo $message; ?>

        <!-- Tab Switcher -->
        <div class="p-1 bg-zinc-100 rounded-xl flex gap-1 mb-5 sm:mb-6 border border-zinc-200">
            <button type="button" id="tabPasswordBtn" onclick="switchAuthTab('password')"
                class="flex-1 py-2 sm:py-2.5 px-3 rounded-lg text-xs sm:text-sm font-bold transition-all bg-zinc-900 text-white shadow-xs flex items-center justify-center gap-1.5 sm:gap-2">
                <i class="fa-solid fa-key text-zinc-300"></i> Password
            </button>
            <button type="button" id="tabFaceBtn" onclick="switchAuthTab('face')"
                class="flex-1 py-2 sm:py-2.5 px-3 rounded-lg text-xs sm:text-sm font-bold transition-all text-zinc-500 hover:text-zinc-900 flex items-center justify-center gap-1.5 sm:gap-2">
                <i class="fa-solid fa-face-viewfinder text-zinc-400"></i> Face ID
            </button>
        </div>

        <!-- Password Auth Form -->
        <div id="passwordAuthPane">
            <form method="POST" autocomplete="on" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5" for="loginEmail">
                        Email Address
                    </label>
                    <div class="relative rounded-xl border border-zinc-300 bg-zinc-50/50 hover:bg-white focus-within:bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" id="loginEmail" name="email"
                            class="w-full pl-10 pr-4 py-2.5 sm:py-3 rounded-xl text-sm sm:text-base text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0"
                            placeholder="name@example.com" required>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider" for="loginPassword">
                            Password
                        </label>
                    </div>
                    <div class="relative rounded-xl border border-zinc-300 bg-zinc-50/50 hover:bg-white focus-within:bg-white focus-within:ring-2 focus-within:ring-zinc-900 focus-within:border-zinc-900 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400 text-sm">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" id="loginPassword" name="password"
                            class="w-full pl-10 pr-11 py-2.5 sm:py-3 rounded-xl text-sm sm:text-base text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0"
                            placeholder="••••••••" required>
                        <button type="button" onclick="togglePasswordVisibility('loginPassword', this)"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-zinc-400 hover:text-zinc-600 focus:outline-none">
                            <i class="fa-regular fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login"
                    class="w-full mt-2 py-3 sm:py-3.5 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold text-sm sm:text-base shadow-xs transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Account
                </button>
            </form>
        </div>

        <!-- Face Biometric Auth Pane -->
        <div id="faceAuthPane" style="display:none;" class="space-y-3 text-center">

            <!-- Instruction tip -->
            <div id="faceInstructionTip" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 font-medium flex items-center justify-center gap-2">
                <i class="fa-solid fa-face-smile text-amber-600 animate-bounce flex-shrink-0"></i>
                <span>Position face in frame and hold steady for automatic login</span>
            </div>

            <!-- Camera Viewfinder -->
            <div id="faceViewfinderWrap" class="relative w-52 h-52 sm:w-60 sm:h-60 mx-auto rounded-2xl overflow-hidden border-2 border-zinc-900 bg-zinc-900 shadow-inner">
                <video id="faceVideo" class="w-full h-full object-cover scale-x-[-1]" autoplay playsinline muted></video>
                <div id="scanReticle" class="absolute inset-3 rounded-[32px] border-2 border-white/30 pointer-events-none transition-colors duration-300"></div>
                <div id="laserLine" class="absolute left-0 right-0 h-0.5 bg-emerald-400 shadow-[0_0_8px_#34d399] animate-laser pointer-events-none"></div>
            </div>

            <!-- Loader Pane (Shown after face capture instead of image box) -->
            <div id="faceLoaderPane" style="display:none;" class="py-10 text-center space-y-4">
                <div class="relative w-16 h-16 mx-auto">
                    <div class="absolute inset-0 rounded-full border-4 border-zinc-200 border-t-zinc-900 animate-spin"></div>
                    <div class="absolute inset-2 rounded-full bg-zinc-50 text-zinc-900 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>
                <p id="faceLoaderText" class="text-sm font-bold text-zinc-900">Verifying identity with AWS Rekognition...</p>
            </div>

            <!-- Status pill -->
            <div id="scanStatus" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                <span>Starting camera...</span>
            </div>

            <!-- Optional email hint -->
            <div id="faceEmailContainer" class="relative rounded-xl border border-zinc-200 bg-zinc-50 text-left">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400 text-sm">
                    <i class="fa-solid fa-at"></i>
                </span>
                <input type="email" id="biometricTargetEmail"
                    class="w-full pl-9 pr-4 py-2.5 rounded-xl text-xs sm:text-sm text-zinc-900 placeholder-zinc-400 bg-transparent border-0 focus:outline-none focus:ring-0"
                    placeholder="Optional: enter your email (speeds up match)">
            </div>

            <p id="faceNoteText" class="text-[10px] text-zinc-400">Fully automatic: no button press needed.</p>
        </div>

        <!-- Footer -->
        <div class="mt-6 sm:mt-8 pt-5 sm:pt-6 border-t border-zinc-100 text-center text-xs sm:text-sm text-zinc-500">
            Don't have an account?
            <a href="register.php" class="font-bold text-zinc-900 hover:underline ml-1">Create Account</a>
        </div>
    </div>
</div>

<script>
const TAB_ACTIVE   = 'flex-1 py-2 sm:py-2.5 px-3 rounded-lg text-xs sm:text-sm font-bold transition-all bg-zinc-900 text-white shadow-xs flex items-center justify-center gap-1.5 sm:gap-2';
const TAB_INACTIVE = 'flex-1 py-2 sm:py-2.5 px-3 rounded-lg text-xs sm:text-sm font-bold transition-all text-zinc-500 hover:text-zinc-900 flex items-center justify-center gap-1.5 sm:gap-2';

function switchAuthTab(mode) {
    const pwBtn   = document.getElementById('tabPasswordBtn');
    const faceBtn = document.getElementById('tabFaceBtn');
    const pwPane  = document.getElementById('passwordAuthPane');
    const facePane = document.getElementById('faceAuthPane');

    if (mode === 'face') {
        pwBtn.className    = TAB_INACTIVE;
        faceBtn.className  = TAB_ACTIVE;
        pwPane.style.display   = 'none';
        facePane.style.display = 'block';
        initFaceLogin();
    } else {
        faceBtn.className  = TAB_INACTIVE;
        pwBtn.className    = TAB_ACTIVE;
        facePane.style.display = 'none';
        pwPane.style.display   = 'block';
        window.biometricEngine.stopCamera();
        resetFaceUI();
    }
}

function resetFaceUI() {
    document.getElementById('faceViewfinderWrap').style.display = 'block';
    document.getElementById('faceLoaderPane').style.display       = 'none';
    document.getElementById('faceInstructionTip').style.display   = 'flex';
    document.getElementById('faceEmailContainer').style.display   = 'block';
    document.getElementById('faceNoteText').style.display         = 'block';
    document.getElementById('scanReticle').style.borderColor      = 'rgba(255,255,255,0.3)';
}

function showFaceLoader(msg) {
    document.getElementById('faceViewfinderWrap').style.display = 'none';
    document.getElementById('faceInstructionTip').style.display   = 'none';
    document.getElementById('faceEmailContainer').style.display   = 'none';
    document.getElementById('faceNoteText').style.display         = 'none';
    document.getElementById('faceLoaderPane').style.display       = 'block';
    if (msg) {
        document.getElementById('faceLoaderText').innerText = msg;
    }
}

async function initFaceLogin() {
    window.biometricEngine.stopCamera();
    resetFaceUI();

    const video   = document.getElementById('faceVideo');
    const status  = document.getElementById('scanStatus');
    const reticle = document.getElementById('scanReticle');

    const ok = await window.biometricEngine.startCamera(video, status, reticle);
    if (!ok) return;

    const emailFilter = document.getElementById('biometricTargetEmail').value.trim() || null;
    const uiRefs = { videoEl: video, statusEl: status, reticleEl: reticle };

    window.biometricEngine.startLoginBlinkScan(
        function(result) {
            window.location.href = result.redirect || 'dashboard.php';
        },
        emailFilter,
        uiRefs
    );
}

// Intercept face capture to hide camera box and display loader
(function() {
    const original = BiometricEngine.prototype.startLoginBlinkScan;
    BiometricEngine.prototype.startLoginBlinkScan = function(onSuccessCallback, emailFilter, uiRefs) {
        const _this = this;

        const wrappedDetect = function(onFaceCaptured, onFrameUpdate) {
            const wrappedCapture = function(captureData) {
                // Show clean loader instead of camera box upon capture
                showFaceLoader('Face captured: verifying identity with AWS Rekognition...');
                if (onFaceCaptured) onFaceCaptured(captureData);
            };
            BiometricEngine.prototype.startBlinkDetection.call(_this, wrappedCapture, onFrameUpdate);
        };

        const origDetect = _this.startBlinkDetection;
        _this.startBlinkDetection = wrappedDetect;
        original.call(_this, onSuccessCallback, emailFilter, uiRefs);
        _this.startBlinkDetection = origDetect;
    };
})();

function togglePasswordVisibility(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (field.type === 'password') {
        field.type    = 'text';
        icon.className = 'fa-regular fa-eye-slash text-sm';
    } else {
        field.type    = 'password';
        icon.className = 'fa-regular fa-eye text-sm';
    }
}
</script>

<?php include_once("../includes/footer.php"); ?>
