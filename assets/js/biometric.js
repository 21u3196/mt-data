/**
 * MT Data AI Biometric Face Engine v3
 *
 * Key design principles:
 *  - Candidate photo captured BEFORE the blink (eyes-open frame)
 *  - Camera is STOPPED and hidden THE INSTANT a blink is detected
 *    (user gets clear "done" signal before API call starts)
 *  - liveness_verified: true sent to backend
 *  - Auto-retry on failed match without camera restart
 */

class BiometricEngine {
  constructor() {
    this.stream        = null;
    this.videoEl       = null;
    this.statusEl      = null;
    this.reticleEl     = null;
    this.isScanning    = false;
    this.scanInterval  = null;

    // Blink state machine
    // IDLE → SEEKING_FACE → WAITING_FOR_BLINK → EYES_CLOSED → BLINK_DETECTED
    this.blinkState          = 'IDLE';
    this.eyeLumaHistory      = [];
    this.baselineEyeContrast = 0;
    this.blinkFramesCount    = 0;
    this._sampleCanvas       = null;
    this._sampleCtx          = null;
  }

  // ── Camera ────────────────────────────────────────────────────────────────

  async startCamera(videoElement, statusElement = null, reticleElement = null) {
    this.videoEl  = videoElement;
    this.statusEl = statusElement;
    this.reticleEl = reticleElement;
    this._resetBlink();

    try {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Webcam not supported in this browser.');
      }
      this.updateStatus('Accessing camera…', 'scanning');
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
        audio: false,
      });
      this.videoEl.srcObject = this.stream;
      await this.videoEl.play();
      this.updateStatus('Camera ready — position your face.', 'scanning');
      return true;
    } catch (err) {
      console.error('Camera error:', err);
      this.updateStatus('Camera error: ' + (err.message || 'Permission denied'), 'error');
      return false;
    }
  }

  /** Stops all tracks and clears video. Does NOT reset blink state (so caller can retry). */
  stopCamera() {
    this.isScanning = false;
    if (this.scanInterval) { clearInterval(this.scanInterval); this.scanInterval = null; }
    if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
    if (this.videoEl) this.videoEl.srcObject = null;
  }

  _resetBlink() {
    this.blinkState          = 'IDLE';
    this.eyeLumaHistory      = [];
    this.baselineEyeContrast = 0;
    this.blinkFramesCount    = 0;
  }

  // ── Blink Frame Analysis ──────────────────────────────────────────────────

  /**
   * Analyse one video frame for blink detection.
   * Uses luminance variance in the eye-band as a proxy for eyelid position.
   * Eyes open  → high contrast (dark iris/pupil against white sclera)
   * Eyes closed → uniform skin tone → contrast drops sharply
   */
  _processFrame() {
    if (!this.videoEl || this.videoEl.readyState < 2) return null;

    const vW = this.videoEl.videoWidth  || 640;
    const vH = this.videoEl.videoHeight || 480;
    const SW = 120, SH = 120;

    if (!this._sampleCanvas) {
      this._sampleCanvas        = document.createElement('canvas');
      this._sampleCanvas.width  = SW;
      this._sampleCanvas.height = SH;
      this._sampleCtx = this._sampleCanvas.getContext('2d', { willReadFrequently: true });
    }

    const crop = Math.min(vW, vH) * 0.72;
    const cx   = (vW - crop) / 2;
    const cy   = (vH - crop) / 2;

    this._sampleCtx.drawImage(this.videoEl, cx, cy, crop, crop, 0, 0, SW, SH);
    const { data: px } = this._sampleCtx.getImageData(0, 0, SW, SH);

    // Eye band: 22%–48% height, 15%–85% width of the cropped square
    const eyeY0 = Math.floor(SH * 0.22), eyeY1 = Math.floor(SH * 0.48);
    const eyeX0 = Math.floor(SW * 0.15), eyeX1 = Math.floor(SW * 0.85);

    let skin = 0, eSum = 0, eSum2 = 0, eCnt = 0;

    for (let y = 0; y < SH; y++) {
      for (let x = 0; x < SW; x++) {
        const i = (y * SW + x) * 4;
        const r = px[i], g = px[i+1], b = px[i+2];
        // YCbCr skin detection
        const Cb = 128 - 0.168736*r - 0.331264*g + 0.5*b;
        const Cr = 128 + 0.5*r - 0.418688*g - 0.081312*b;
        if (r>40 && g>25 && r>b && Cb>=75 && Cb<=135 && Cr>=125 && Cr<=180) skin++;
        if (y>=eyeY0 && y<=eyeY1 && x>=eyeX0 && x<=eyeX1) {
          const Y = 0.299*r + 0.587*g + 0.114*b;
          eSum += Y; eSum2 += Y*Y; eCnt++;
        }
      }
    }

    if (skin / (SW*SH) < 0.18) {
      this.blinkState = 'SEEKING_FACE';
      return { state: 'SEEKING_FACE' };
    }

    const mean     = eSum / Math.max(1, eCnt);
    const variance = Math.max(0, eSum2 / Math.max(1, eCnt) - mean*mean);
    const contrast = Math.sqrt(variance);

    this.eyeLumaHistory.push(contrast);
    if (this.eyeLumaHistory.length > 10) this.eyeLumaHistory.shift();

    // State transitions
    if (this.blinkState === 'IDLE' || this.blinkState === 'SEEKING_FACE') {
      if (this.eyeLumaHistory.length >= 5) {
        this.baselineEyeContrast = this.eyeLumaHistory.reduce((a,b)=>a+b,0) / this.eyeLumaHistory.length;
        this.blinkState = 'WAITING_FOR_BLINK';
      }
      return { state: 'WAITING_FOR_BLINK' };
    }

    if (this.blinkState === 'WAITING_FOR_BLINK') {
      if (contrast < this.baselineEyeContrast * 0.76 && this.baselineEyeContrast > 8) {
        this.blinkState = 'EYES_CLOSED';
        this.blinkFramesCount = 1;
        return { state: 'EYES_CLOSED' };
      }
      return { state: 'WAITING_FOR_BLINK' };
    }

    if (this.blinkState === 'EYES_CLOSED') {
      this.blinkFramesCount++;
      if (contrast >= this.baselineEyeContrast * 0.85 || this.blinkFramesCount >= 3) {
        this.blinkState = 'BLINK_DETECTED';
        return { state: 'BLINK_DETECTED' };
      }
      return { state: 'EYES_CLOSED' };
    }

    return { state: this.blinkState };
  }

  // ── Blink Detection Loop ──────────────────────────────────────────────────

  /**
   * Start the blink detection scan.
   *
   * CRITICAL: candidatePhoto is captured while eyes are OPEN (WAITING_FOR_BLINK).
   * This gives the server an eyes-open image for liveness validation.
   *
   * The moment BLINK_DETECTED fires:
   *  1. Interval is immediately cleared (stops scanning)
   *  2. Camera tracks are stopped (light turns off — clear "done" signal)
   *  3. onBlinkCaptured is called with the candidate photo
   *
   * This means the camera shuts off BEFORE the API call starts.
   */
  startBlinkDetection(onBlinkCaptured, onFrameUpdate = null) {
    if (this.isScanning) return;
    this.isScanning     = true;
    this.blinkState     = 'SEEKING_FACE';
    this.eyeLumaHistory = [];

    let candidatePhoto      = null;
    let candidateDescriptor = null;
    let lastCandidateMs     = 0;

    this.scanInterval = setInterval(() => {
      if (!this.isScanning) return;

      const res = this._processFrame();

      // Capture candidate every 300ms while eyes are open & face aligned
      if (res && res.state === 'WAITING_FOR_BLINK') {
        const now = Date.now();
        if (now - lastCandidateMs > 300) {
          lastCandidateMs = now;
          const photo = this.capturePhoto();
          const fd    = this.extractDescriptor();
          if (photo) {
            candidatePhoto      = photo;
            candidateDescriptor = fd ? fd.descriptor : null;
          }
        }
      }

      if (onFrameUpdate && res) onFrameUpdate(res);

      if (res && res.state === 'BLINK_DETECTED') {
        // ── BLINK CONFIRMED — stop everything immediately ─────────────────
        this.isScanning = false;
        clearInterval(this.scanInterval);
        this.scanInterval = null;

        // Stop camera tracks NOW (camera light goes off — user knows it's done)
        if (this.stream) {
          this.stream.getTracks().forEach(t => t.stop());
          this.stream = null;
        }
        if (this.videoEl) this.videoEl.srcObject = null;

        // Use eyes-open candidate frame (captured before the blink)
        // Fallback only if candidate was never stored (edge case)
        const photo      = candidatePhoto      || null;
        const descriptor = candidateDescriptor || null;

        if (onBlinkCaptured) {
          onBlinkCaptured({ photo, descriptor, liveness_verified: true });
        }
      }
    }, 100); // 10 fps
  }

  // ── Login Flow ────────────────────────────────────────────────────────────

  /**
   * Automatic login: camera → blink → API → redirect.
   * On blink: camera stops immediately, status shows "verifying".
   * On failed match: camera restarts for another attempt.
   * On network error: camera restarts for retry.
   *
   * @param {Function|null} onSuccessCallback  Optional custom success handler
   * @param {string|null}   emailFilter        Optional email to narrow match
   * @param {object}        uiRefs             { videoEl, statusEl, reticleEl } — needed for retry restarts
   */
  startLoginBlinkScan(onSuccessCallback, emailFilter, uiRefs) {
    this.startBlinkDetection(
      // ── onBlinkCaptured ─────────────────────────────────────────────────
      async (blinkData) => {
        // Camera is already stopped. Update UI immediately.
        this.updateStatus('Blink verified — verifying identity…', 'scanning');
        if (this.reticleEl) this.reticleEl.style.borderColor = '#10b981';

        if (!blinkData.photo) {
          this.updateStatus('Could not capture face photo. Please try again.', 'error');
          setTimeout(() => this._retryLogin(onSuccessCallback, emailFilter, uiRefs), 2500);
          return;
        }

        try {
          const response = await fetch('../api/face_auth.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
              face_photo:        blinkData.photo,
              face_descriptor:   blinkData.descriptor,
              liveness_verified: true,
              email:             emailFilter,
            }),
          });

          const result = await response.json();

          if (result.success) {
            this.updateStatus('Identity verified! Redirecting…', 'success');
            setTimeout(() => {
              if (onSuccessCallback) {
                onSuccessCallback(result);
              } else {
                window.location.href = result.redirect || 'dashboard.php';
              }
            }, 600);

          } else {
            this.updateStatus(result.message || 'Face not recognized.', 'error');
            if (this.reticleEl) this.reticleEl.style.borderColor = '#ef4444';
            setTimeout(() => this._retryLogin(onSuccessCallback, emailFilter, uiRefs), 3000);
          }
        } catch (err) {
          console.error('Auth fetch error:', err);
          this.updateStatus('Network error — retrying…', 'error');
          setTimeout(() => this._retryLogin(onSuccessCallback, emailFilter, uiRefs), 3000);
        }
      },

      // ── onFrameUpdate ────────────────────────────────────────────────────
      (res) => {
        if (!res) return;
        if (res.state === 'SEEKING_FACE') {
          this.updateStatus('Position your face in the frame', 'scanning');
          if (this.reticleEl) this.reticleEl.style.borderColor = 'rgba(255,255,255,0.3)';
        } else if (res.state === 'WAITING_FOR_BLINK') {
          this.updateStatus('Face detected — BLINK to sign in', 'scanning');
          if (this.reticleEl) this.reticleEl.style.borderColor = '#f59e0b';
        } else if (res.state === 'EYES_CLOSED' || res.state === 'BLINK_DETECTED') {
          this.updateStatus('Blink detected — capturing…', 'success');
          if (this.reticleEl) this.reticleEl.style.borderColor = '#10b981';
        }
      }
    );
  }

  async _retryLogin(onSuccessCallback, emailFilter, uiRefs) {
    if (!uiRefs) return;
    this._resetBlink();
    this.updateStatus('Restarting camera for another attempt…', 'scanning');
    if (this.reticleEl) this.reticleEl.style.borderColor = 'rgba(255,255,255,0.3)';

    // Restart camera
    const ok = await this.startCamera(uiRefs.videoEl, uiRefs.statusEl, uiRefs.reticleEl);
    if (ok) {
      this.startLoginBlinkScan(onSuccessCallback, emailFilter, uiRefs);
    }
  }

  // ── Photo / Descriptor ────────────────────────────────────────────────────

  capturePhoto() {
    if (!this.videoEl || this.videoEl.readyState < 2) return null;
    const canvas  = document.createElement('canvas');
    canvas.width  = this.videoEl.videoWidth  || 640;
    canvas.height = this.videoEl.videoHeight || 480;
    canvas.getContext('2d').drawImage(this.videoEl, 0, 0);
    return canvas.toDataURL('image/jpeg', 0.93);
  }

  /** 128-D HOG descriptor — local fallback when AWS is unavailable */
  extractDescriptor(W = 128, H = 128) {
    if (!this.videoEl || this.videoEl.readyState < 2) return null;

    const vW = this.videoEl.videoWidth || 640, vH = this.videoEl.videoHeight || 480;
    const canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    const crop = Math.min(vW, vH) * 0.72;
    ctx.drawImage(this.videoEl, (vW-crop)/2, (vH-crop)/2, crop, crop, 0, 0, W, H);

    const { data } = ctx.getImageData(0, 0, W, H);
    const gray = new Float32Array(W * H);
    for (let i = 0; i < data.length; i += 4) {
      gray[i/4] = (0.299*data[i] + 0.587*data[i+1] + 0.114*data[i+2]) / 255;
    }

    const desc = new Array(128).fill(0);
    const cW   = Math.floor(W/4), cH = Math.floor(H/4);

    for (let cy = 0; cy < 4; cy++) {
      for (let cx = 0; cx < 4; cx++) {
        const base = (cy*4+cx)*8;
        for (let y = cy*cH+1; y < (cy+1)*cH-1; y++) {
          for (let x = cx*cW+1; x < (cx+1)*cW-1; x++) {
            const idx = y*W + x;
            const dx  = gray[idx+1] - gray[idx-1];
            const dy  = gray[idx+W]  - gray[idx-W];
            const mag = Math.sqrt(dx*dx + dy*dy);
            let   ang = Math.atan2(dy, dx);
            if (ang < 0) ang += Math.PI*2;
            desc[base + Math.min(7, Math.floor(ang/(Math.PI*2)*8))] += mag;
          }
        }
      }
    }

    let sq = 0;
    for (let i = 0; i < 128; i++) sq += desc[i]*desc[i];
    const n = Math.sqrt(sq) || 1;
    for (let i = 0; i < 128; i++) desc[i] = Number((desc[i]/n).toFixed(6));

    return { descriptor: desc };
  }

  // ── Status UI ─────────────────────────────────────────────────────────────

  updateStatus(msg, type = 'scanning') {
    if (!this.statusEl) return;
    const cls = {
      success: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
      error:   'bg-red-50 text-red-700 border border-red-200',
      scanning:'bg-amber-50 text-amber-800 border border-amber-200',
    };
    const dot = {
      success: 'bg-emerald-500',
      error:   'bg-red-500',
      scanning:'bg-amber-500 animate-ping',
    };
    this.statusEl.className = `inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold ${cls[type]||cls.scanning}`;
    this.statusEl.innerHTML = `<span class="w-2 h-2 rounded-full ${dot[type]||dot.scanning}"></span><span>${msg}</span>`;
  }
}

window.biometricEngine = new BiometricEngine();
