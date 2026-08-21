<?php
include_once("../config.php");
if (!is_logged_in()) redirect("login.php");

$user     = get_current_user_data();
$has_face = !empty($user['face_enrolled_at']);

$page_title = "Face ID Biometric Enrollment";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 sm:py-12 px-3 sm:px-6 lg:px-8 bg-zinc-50">
    <div class="max-w-lg mx-auto">
        <div class="bg-white rounded-2xl p-5 sm:p-8 shadow-sm border border-zinc-200 relative overflow-hidden">

            <!-- Header -->
            <div class="text-center mb-6">
                <div class="w-12 h-12 rounded-xl bg-zinc-900 text-white flex items-center justify-center mx-auto text-lg shadow-sm mb-3">
                    <i class="fa-solid fa-face-viewfinder"></i>
                </div>
                <h1 class="font-heading text-xl sm:text-2xl font-bold text-zinc-900 tracking-tight">AI Face ID Enrollment</h1>
                <p class="text-xs sm:text-sm text-zinc-500 mt-1 max-w-sm mx-auto">Automatic Hold &amp; Capture . AWS Rekognition Liveness</p>
            </div>

            <!-- Profile Status Bar -->
            <div class="p-3.5 rounded-xl bg-zinc-100/80 border border-zinc-200 flex items-center justify-between gap-3 mb-6">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl overflow-hidden bg-zinc-800 text-white flex-shrink-0 flex items-center justify-center font-bold text-sm">
                        <?php if (!empty($user['face_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user['face_photo']); ?>" alt="" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($user['fullname'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-heading text-xs sm:text-sm font-bold text-zinc-900 truncate"><?php echo htmlspecialchars($user['fullname']); ?></h4>
                        <?php if ($has_face): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 mt-0.5">
                                <i class="fa-solid fa-circle-check text-[9px]"></i> Active (Enrolled <?php echo date('M d, Y', strtotime($user['face_enrolled_at'])); ?>)
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 mt-0.5">
                                <i class="fa-solid fa-triangle-exclamation text-[9px]"></i> Not Enrolled
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($has_face): ?>
                    <button onclick="removeFace()" class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 transition-colors flex-shrink-0">
                        <i class="fa-solid fa-trash-can mr-1"></i>Reset
                    </button>
                <?php endif; ?>
            </div>

            <!-- STAGE 1: Instructions -->
            <div id="stage1" class="space-y-4">
                <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-2.5">
                    <p class="font-bold text-sm text-amber-950 flex items-center gap-1.5">
                        <i class="fa-solid fa-camera text-amber-600"></i> Automatic Face Verification
                    </p>
                    <ul class="space-y-2 ml-1">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-1 text-amber-700 font-bold mt-0.5"></i>
                            <span>Click <strong>Start</strong> and allow camera access.</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-2 text-amber-700 font-bold mt-0.5"></i>
                            <span>Position your face inside the oval frame with good lighting.</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-3 text-amber-700 font-bold mt-0.5"></i>
                            <span>Hold steady for <strong>2 seconds</strong>. The photo is captured automatically.</span></li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-4 text-amber-700 font-bold mt-0.5"></i>
                            <span>AWS Rekognition verifies face quality and duplicate detection server-side.</span></li>
                    </ul>
                </div>

                <button onclick="startEnrollment()" id="startBtn"
                    class="w-full py-3.5 px-6 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2.5">
                    <i class="fa-solid fa-camera"></i> Start Face ID Enrollment
                </button>
            </div>

            <!-- STAGE 2: Camera Viewfinder -->
            <div id="stage2" style="display:none;" class="space-y-3">

                <!-- Status banner -->
                <div id="blinkBanner"
                    class="p-3 rounded-xl font-bold text-xs sm:text-sm text-center flex items-center justify-center gap-2 shadow-sm bg-zinc-700 text-white">
                    <i class="fa-solid fa-circle-notch animate-spin text-base"></i>
                    <span id="blinkBannerText">Starting camera...</span>
                </div>

                <!-- Camera box -->
                <div class="relative w-full max-w-xs mx-auto aspect-square rounded-2xl overflow-hidden border-2 border-zinc-900 bg-zinc-900 shadow-md">
                    <video id="enrollVideo" class="w-full h-full object-cover scale-x-[-1]" autoplay playsinline muted></video>

                    <!-- Static snapshot canvas (shown after capture) -->
                    <canvas id="enrollSnapshot" class="absolute inset-0 w-full h-full object-cover scale-x-[-1] hidden"></canvas>

                    <!-- "Captured!" overlay (shown after capture) -->
                    <div id="capturedOverlay" class="absolute inset-0 bg-emerald-900/75 hidden flex-col items-center justify-center gap-3">
                        <i class="fa-solid fa-circle-check text-white text-5xl"></i>
                        <p class="text-white text-sm font-bold">Captured! Processing...</p>
                    </div>

                    <!-- Oval reticle -->
                    <div id="enrollReticle" class="absolute inset-4 rounded-[38px] border-2 border-white/30 pointer-events-none transition-colors duration-300"></div>

                    <!-- Circular Progress Ring SVG -->
                    <svg class="absolute inset-0 w-full h-full pointer-events-none p-4" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="46" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="3" />
                        <circle id="progressCircle" cx="50" cy="50" r="46" fill="none" stroke="#10b981" stroke-width="4"
                                stroke-dasharray="289" stroke-dashoffset="289" stroke-linecap="round"
                                class="transition-all duration-150 transform -rotate-90 origin-center" />
                    </svg>
                </div>

                <!-- Status pill row -->
                <div class="flex items-center justify-between text-xs font-semibold px-1">
                    <span id="enrollStatusPill" class="inline-flex items-center gap-1.5 text-zinc-500">
                        <span class="w-2 h-2 rounded-full bg-zinc-400"></span> Waiting for camera...
                    </span>
                    <button onclick="cancelEnrollment()" class="text-zinc-400 hover:text-red-500 text-[11px] underline">Cancel</button>
                </div>
            </div>

            <!-- STAGE 3: Processing / AWS check -->
            <div id="stage3" style="display:none;" class="py-12 text-center space-y-4">
                <div class="relative w-16 h-16 mx-auto">
                    <div class="absolute inset-0 rounded-full border-4 border-zinc-200 border-t-zinc-900 animate-spin"></div>
                    <div class="absolute inset-2 rounded-full bg-zinc-50 text-zinc-900 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>
                <h3 class="font-heading text-base font-bold text-zinc-900">AWS Rekognition Verification</h3>
                <p id="processText" class="text-xs text-zinc-500">Checking liveness, face quality and duplicate accounts...</p>
            </div>

            <!-- Back link -->
            <div class="mt-6 pt-4 border-t border-zinc-100 text-center">
                <a href="dashboard.php" class="text-xs font-semibold text-zinc-500 hover:text-zinc-900 transition-colors inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
    <div class="bg-white rounded-2xl p-7 max-w-sm w-full shadow-xl border border-zinc-200 text-center transform scale-95 transition-all duration-300" id="successBox">
        <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-2xl mx-auto mb-4">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2 class="font-heading text-lg font-bold text-zinc-900 mb-1">Face ID Enrolled!</h2>
        <p id="successText" class="text-xs sm:text-sm text-zinc-600 mb-4">Your facial profile was verified by AWS Rekognition.</p>
        <div class="w-full h-1.5 bg-zinc-100 rounded-full overflow-hidden">
            <div id="successBar" class="h-full bg-emerald-600 transition-all duration-[2500ms] ease-linear" style="width:0%"></div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div id="errorModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
    <div class="bg-white rounded-2xl p-7 max-w-sm w-full shadow-xl border border-zinc-200 text-center">
        <div class="w-14 h-14 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center text-2xl mx-auto mb-4">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h2 class="font-heading text-lg font-bold text-zinc-900 mb-2">Enrollment Blocked</h2>
        <p id="errorText" class="text-xs sm:text-sm text-zinc-600 mb-5">Something went wrong.</p>
        <button onclick="retryEnrollment()"
            class="w-full py-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm transition-all">
            <i class="fa-solid fa-rotate-right mr-1.5"></i> Try Again
        </button>
    </div>
</div>

<script>
function setBanner(text, style) {
    const banner = document.getElementById('blinkBanner');
    const span   = document.getElementById('blinkBannerText');
    if (!banner || !span) return;
    const map = {
        idle:     'p-3 rounded-xl font-bold text-xs sm:text-sm text-center flex items-center justify-center gap-2 shadow-sm bg-zinc-700 text-white',
        seeking:  'p-3 rounded-xl font-bold text-xs sm:text-sm text-center flex items-center justify-center gap-2 shadow-sm bg-zinc-800 text-white',
        ready:    'p-3 rounded-xl font-bold text-xs sm:text-sm text-center flex items-center justify-center gap-2 shadow-sm bg-amber-500 text-white',
        detected: 'p-3 rounded-xl font-bold text-xs sm:text-sm text-center flex items-center justify-center gap-2 shadow-sm bg-emerald-600 text-white',
    };
    const icons = {
        idle:     '<i class="fa-solid fa-circle-notch animate-spin text-base"></i>',
        seeking:  '<i class="fa-solid fa-eye text-base animate-bounce"></i>',
        ready:    '<i class="fa-solid fa-face-smile text-base"></i>',
        detected: '<i class="fa-solid fa-circle-check text-base"></i>',
    };
    banner.className = map[style] || map.idle;
    banner.innerHTML = (icons[style] || '') + '<span id="blinkBannerText">' + text + '</span>';
}

function setReticle(color) {
    const r = document.getElementById('enrollReticle');
    if (r) r.style.borderColor = color;
}

function setPill(text, dot) {
    const pill = document.getElementById('enrollStatusPill');
    if (!pill) return;
    const dotCls = dot === 'amber' ? 'bg-amber-500 animate-ping'
                 : dot === 'green' ? 'bg-emerald-500'
                 : 'bg-zinc-400';
    pill.innerHTML = `<span class="w-2 h-2 rounded-full ${dotCls}"></span> ${text}`;
}

function freezeEnrollView(photoDataUrl) {
    const video    = document.getElementById('enrollVideo');
    const snapshot = document.getElementById('enrollSnapshot');
    const overlay  = document.getElementById('capturedOverlay');

    if (photoDataUrl) {
        const img = new Image();
        img.onload = () => {
            snapshot.width  = img.width  || 300;
            snapshot.height = img.height || 300;
            snapshot.getContext('2d').drawImage(img, 0, 0, snapshot.width, snapshot.height);
        };
        img.src = photoDataUrl;
    }

    video.classList.add('hidden');
    snapshot.classList.remove('hidden');
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    setReticle('#10b981');
}

// ── Enrollment Controller ──────────────────────────────────────────────────────

async function startEnrollment() {
    document.getElementById('stage1').style.display = 'none';
    document.getElementById('stage2').style.display = 'block';
    setBanner('Starting camera...', 'idle');

    const video    = document.getElementById('enrollVideo');
    const status   = document.getElementById('enrollStatusPill');
    const reticle  = document.getElementById('enrollReticle');
    const progress = document.getElementById('progressCircle');

    const ok = await window.biometricEngine.startCamera(video, status, reticle, progress);
    if (!ok) {
        showEnrollError('Camera access denied. Please allow camera permissions in your browser and try again.');
        cancelEnrollment();
        return;
    }

    setBanner('Position your face inside the oval', 'seeking');
    setReticle('rgba(255,255,255,0.3)');
    setPill('Looking for face...', 'amber');

    window.biometricEngine.startFaceCapture(

        function(captureData) {
            freezeEnrollView(captureData.photo);
            setBanner('Face captured! Sending to AWS Rekognition...', 'detected');
            setPill('Captured!', 'green');

            setTimeout(function() {
                document.getElementById('stage2').style.display = 'none';
                document.getElementById('stage3').style.display = 'block';
                submitEnrollment(captureData.photo, captureData.descriptor, captureData.liveness_verified);
            }, 600);
        },

        function(res) {
            if (!res) return;
            if (res.state === 'NO_FACE') {
                setBanner('Position your face inside the oval', 'seeking');
                setReticle('rgba(255,255,255,0.3)');
                setPill('Looking for face...', 'amber');
            } else if (res.state === 'FACE_DETECTED') {
                const remaining = ((2500 - (res.progress / 100) * 2500) / 1000).toFixed(1);
                setBanner(`Face detected: hold still (${remaining}s)`, 'ready');
                setReticle('#10b981');
                setPill('Holding face steady...', 'green');
            }
        }
    );
}

async function submitEnrollment(photoDataUrl, descriptor, livenessVerified) {
    if (!photoDataUrl) {
        showEnrollError('No face photo was captured. Please try again.');
        return;
    }

    try {
        const resp = await fetch('../api/enroll_face.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                face_photo:        photoDataUrl,
                face_descriptor:   descriptor,
                liveness_verified: livenessVerified === true,
            }),
        });

        const data = await resp.json();
        if (data.success) {
            showEnrollSuccess(data.message);
        } else {
            showEnrollError(data.message || 'Enrollment failed. Please try again.');
        }
    } catch (err) {
        console.error('Enrollment submit error:', err);
        showEnrollError('Could not reach the verification server. Check your connection and try again.');
    }
}

function showEnrollSuccess(msg) {
    document.getElementById('stage3').style.display = 'none';
    document.getElementById('successText').innerText = msg || 'Face ID enrolled successfully!';
    const modal = document.getElementById('successModal');
    const box   = document.getElementById('successBox');
    modal.classList.remove('opacity-0', 'pointer-events-none');
    box.classList.remove('scale-95');
    box.classList.add('scale-100');
    setTimeout(() => document.getElementById('successBar').style.width = '100%', 50);
    setTimeout(() => window.location.href = 'dashboard.php', 2700);
}

function showEnrollError(msg) {
    window.biometricEngine.stopCamera();
    document.getElementById('stage2').style.display = 'none';
    document.getElementById('stage3').style.display = 'none';
    document.getElementById('errorText').innerText  = msg;
    document.getElementById('errorModal').classList.remove('opacity-0', 'pointer-events-none');
}

function retryEnrollment() {
    document.getElementById('errorModal').classList.add('opacity-0', 'pointer-events-none');
    document.getElementById('enrollVideo').classList.remove('hidden');
    document.getElementById('enrollSnapshot').classList.add('hidden');
    document.getElementById('capturedOverlay').classList.add('hidden');
    document.getElementById('capturedOverlay').classList.remove('flex');
    document.getElementById('stage2').style.display = 'none';
    document.getElementById('stage3').style.display = 'none';
    document.getElementById('stage1').style.display = 'block';
}

function cancelEnrollment() {
    window.biometricEngine.stopCamera();
    document.getElementById('enrollVideo').classList.remove('hidden');
    document.getElementById('enrollSnapshot').classList.add('hidden');
    document.getElementById('capturedOverlay').classList.add('hidden');
    document.getElementById('capturedOverlay').classList.remove('flex');
    document.getElementById('stage2').style.display = 'none';
    document.getElementById('stage3').style.display = 'none';
    document.getElementById('stage1').style.display = 'block';
}

async function removeFace() {
    if (!confirm('Remove your enrolled Face ID? You will need to re-enroll to use biometric login.')) return;
    try {
        const r = await fetch('../api/remove_face.php', { method: 'POST' });
        const d = await r.json();
        if (d.success) location.reload();
        else alert(d.message || 'Failed to remove biometrics.');
    } catch (e) { alert('Network error. Please try again.'); }
}
</script>

<?php include_once("../includes/footer.php"); ?>
