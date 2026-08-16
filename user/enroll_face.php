<?php
include_once("../config.php");

if (!is_logged_in()) {
    redirect("login.php");
}

$user = get_current_user_data();
$has_face = !empty($user['face_descriptor']);

$page_title = "Face ID Biometrics";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-xl mx-auto">
        
        <div class="bg-white/95 backdrop-blur-xl rounded-3xl p-6 sm:p-10 shadow-2xl ring-1 ring-slate-900/5 border border-slate-100">
            
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-12 h-12 rounded-2xl bg-brand-100 text-brand-600 flex items-center justify-center mx-auto text-xl shadow-md shadow-brand-500/10 mb-4">
                    <i class="fa-solid fa-face-viewfinder"></i>
                </div>
                <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Biometric Face ID</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Fast, passwordless AI authentication for your account</p>
            </div>

            <!-- Current Enrollment Status Card -->
            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="relative w-12 h-12 rounded-2xl overflow-hidden bg-brand-600 text-white flex-shrink-0 flex items-center justify-center font-bold text-base shadow-md">
                        <?php if (!empty($user['face_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user['face_photo']); ?>" alt="Enrolled Face" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($user['fullname'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-heading text-sm font-bold text-slate-900 truncate"><?php echo htmlspecialchars($user['fullname']); ?></h4>
                        <?php if ($has_face): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 mt-0.5">
                                <i class="fa-solid fa-circle-check text-[10px]"></i> Active (Enrolled <?php echo date('M d, Y', strtotime($user['face_enrolled_at'] ?? 'now')); ?>)
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 mt-0.5">
                                <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> No Face Profile Enrolled
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($has_face): ?>
                    <button type="button" onclick="removeFaceProfile()" class="px-3 py-1.5 rounded-xl text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 transition-colors flex-shrink-0">
                        <i class="fa-solid fa-trash-can mr-1"></i> Remove
                    </button>
                <?php endif; ?>
            </div>

            <!-- Interactive Enrollment Camera Section -->
            <div class="space-y-6 text-center">
                <div class="relative w-64 h-64 mx-auto rounded-3xl overflow-hidden border-2 border-dashed border-brand-300 bg-slate-950 flex items-center justify-center shadow-inner group">
                    <video id="enrollVideo" class="w-full h-full object-cover" autoplay playsinline muted></video>
                    
                    <!-- Scan Reticle -->
                    <div id="enrollReticle" class="absolute inset-5 rounded-2xl border-2 border-dashed border-brand-400/40 pointer-events-none transition-all"></div>
                    
                    <!-- Laser Sweep -->
                    <div class="absolute left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-accent-400 to-transparent shadow-[0_0_8px_#ec4899] animate-laser pointer-events-none"></div>
                </div>

                <div id="enrollStatus" class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                    <span>Click 'Start Camera Preview' to align your face</span>
                </div>

                <div class="space-y-3 pt-2">
                    <button type="button" id="btnStartEnrollCamera" onclick="initEnrollCamera()" class="w-full py-3.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm shadow-md transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-video text-brand-400"></i> Start Camera Preview
                    </button>

                    <button type="button" id="btnCaptureEnroll" onclick="captureAndEnroll()" style="display: none;" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-sm shadow-lg shadow-emerald-500/25 hover:shadow-glow-emerald transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-camera"></i> Capture & Save Face Biometrics
                    </button>
                </div>
            </div>

            <!-- Back Link -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <a href="dashboard.php" class="text-xs sm:text-sm font-semibold text-slate-500 hover:text-slate-900 transition-colors inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Return to Dashboard
                </a>
            </div>

        </div>

    </div>
</div>

<script>
async function initEnrollCamera() {
    const video = document.getElementById('enrollVideo');
    const status = document.getElementById('enrollStatus');
    const reticle = document.getElementById('enrollReticle');
    const btnStart = document.getElementById('btnStartEnrollCamera');
    const btnCapture = document.getElementById('btnCaptureEnroll');

    const ok = await window.biometricEngine.startCamera(video, status, reticle);
    if (ok) {
        btnStart.style.display = 'none';
        btnCapture.style.display = 'flex';
        reticle.style.borderColor = '#10b981';
        window.biometricEngine.updateStatus("Look directly into the lens and click Capture", "scanning");
    }
}

async function captureAndEnroll() {
    const btnCapture = document.getElementById('btnCaptureEnroll');
    btnCapture.disabled = true;
    window.biometricEngine.updateStatus("Extracting 128-D facial vector...", "scanning");

    const faceData = window.biometricEngine.extractFaceDescriptor();
    if (!faceData) {
        window.biometricEngine.updateStatus("Could not extract facial geometry. Please ensure clear lighting.", "error");
        btnCapture.disabled = false;
        return;
    }

    try {
        const response = await fetch('../api/enroll_face.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                face_descriptor: faceData.descriptor,
                face_photo: faceData.thumbnail
            })
        });

        const result = await response.json();

        if (result.success) {
            window.biometricEngine.updateStatus("Face ID Enrolled Successfully!", "success");
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } else {
            window.biometricEngine.updateStatus(result.message || "Failed to enroll face profile.", "error");
            btnCapture.disabled = false;
        }
    } catch (err) {
        console.error("Enrollment error:", err);
        window.biometricEngine.updateStatus("Network communication error.", "error");
        btnCapture.disabled = false;
    }
}

async function removeFaceProfile() {
    if (!confirm("Are you sure you want to remove your enrolled Face ID? You will need to sign in using your password.")) {
        return;
    }

    try {
        const response = await fetch('../api/remove_face.php', { method: 'POST' });
        const result = await response.json();
        if (result.success) {
            window.location.reload();
        } else {
            alert(result.message || "Failed to remove biometrics.");
        }
    } catch (err) {
        alert("Network error.");
    }
}
</script>

<?php include_once("../includes/footer.php"); ?>
