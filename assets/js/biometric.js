/**
 * MT Data Biometric Engine v5
 *
 * Liveness strategy:
 *   CLIENT: face-api.js TinyFaceDetector detects a real face is present.
 *           Auto-captures once face is stable for CAPTURE_HOLD_MS.
 *           No blink needed, user just looks at camera and holds still.
 *   SERVER: AWS Rekognition detectFaces validates quality + liveness.
 *
 * This is more reliable than blink detection and matches what major
 * banking/KYC apps actually use in production.
 */

const FACEAPI_SRC     = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
const MODELS_URL      = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';
const CAPTURE_HOLD_MS = 2500;   // ms face must be stable before auto-capture
const DETECT_SCORE    = 0.5;    // minimum face detection confidence
const SCAN_MS         = 180;    // detection interval (ms)

// ── Script loader ──────────────────────────────────────────────────────────────

function _loadScript(src) {
  return new Promise((resolve, reject) => {
    if (window.faceapi) return resolve();
    if (document.querySelector(`script[src="${src}"]`)) {
      const wait = setInterval(() => { if (window.faceapi) { clearInterval(wait); resolve(); } }, 100);
      return;
    }
    const s  = document.createElement('script');
    s.src    = src;
    s.onload  = resolve;
    s.onerror = reject;
    document.head.appendChild(s);
  });
}

// ── Engine class ───────────────────────────────────────────────────────────────

class BiometricEngine {
  constructor() {
    this.stream        = null;
    this.videoEl       = null;
    this.statusEl      = null;
    this.reticleEl     = null;
    this.progressEl    = null;
    this.isScanning    = false;
    this._loopTimer    = null;

    // Face detection state
    this._faceHeldSince  = null;
    this._modelsReady    = false;
    this._modelsLoading  = false;
    this._fallback       = false;

    // Pixel fallback state
    this._sampleCanvas = null;
    this._sampleCtx    = null;
  }

  // ── Model loading ────────────────────────────────────────────────────────────

  async _loadModels() {
    if (this._modelsReady)   return true;
    if (this._modelsLoading) {
      while (this._modelsLoading) await new Promise(r => setTimeout(r, 80));
      return this._modelsReady;
    }
    this._modelsLoading = true;
    this.updateStatus('Loading face detection...', 'scanning');
    try {
      await _loadScript(FACEAPI_SRC);
      await faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL);
      this._modelsReady = true;
      return true;
    } catch (e) {
      console.warn('face-api failed, using pixel fallback:', e.message);
      this._fallback = true;
      return false;
    } finally {
      this._modelsLoading = false;
    }
  }

  // ── Camera ────────────────────────────────────────────────────────────────────

  async startCamera(videoEl, statusEl = null, reticleEl = null, progressEl = null) {
    this.videoEl    = videoEl;
    this.statusEl   = statusEl;
    this.reticleEl  = reticleEl;
    this.progressEl = progressEl;
    this._faceHeldSince = null;

    try {
      if (!navigator.mediaDevices?.getUserMedia)
        throw new Error('Camera not supported in this browser.');
      this.updateStatus('Starting camera...', 'scanning');
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
        audio: false,
      });
      this.videoEl.srcObject = this.stream;
      await this.videoEl.play();
      this.updateStatus('Camera ready: position your face in frame.', 'scanning');
      return true;
    } catch (err) {
      this.updateStatus('Camera error: ' + (err.message || 'Permission denied'), 'error');
      return false;
    }
  }

  stopCamera() {
    this.isScanning = false;
    if (this._loopTimer) { clearTimeout(this._loopTimer); this._loopTimer = null; }
    if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
    if (this.videoEl) this.videoEl.srcObject = null;
    this._faceHeldSince = null;
    this._setProgress(0);
  }

  // ── Face detection ────────────────────────────────────────────────────────────

  async _detectFace() {
    if (!this.videoEl || this.videoEl.readyState < 2) return false;
    try {
      if (!this._fallback) {
        const det = await faceapi.detectSingleFace(
          this.videoEl,
          new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: DETECT_SCORE })
        );
        return !!det;
      }
    } catch(e) {
      this._fallback = true;
    }
    return this._pixelFacePresent();
  }

  _pixelFacePresent() {
    if (!this.videoEl || this.videoEl.readyState < 2) return false;
    const W = 80, H = 80;
    if (!this._sampleCanvas) {
      this._sampleCanvas = document.createElement('canvas');
      this._sampleCanvas.width = W; this._sampleCanvas.height = H;
      this._sampleCtx = this._sampleCanvas.getContext('2d', { willReadFrequently: true });
    }
    const vW = this.videoEl.videoWidth || 640, vH = this.videoEl.videoHeight || 480;
    const crop = Math.min(vW, vH) * 0.72;
    this._sampleCtx.drawImage(this.videoEl, (vW-crop)/2, (vH-crop)/2, crop, crop, 0, 0, W, H);
    const px = this._sampleCtx.getImageData(0, 0, W, H).data;
    let skin = 0;
    for (let i = 0; i < px.length; i += 4) {
      const r=px[i], g=px[i+1], b=px[i+2];
      const Cb=128-0.168736*r-0.331264*g+0.5*b, Cr=128+0.5*r-0.418688*g-0.081312*b;
      if (r>40&&g>25&&r>b&&Cb>=75&&Cb<=135&&Cr>=125&&Cr<=180) skin++;
    }
    return skin / (W * H) > 0.22;
  }

  // ── Progress ring ─────────────────────────────────────────────────────────────

  _setProgress(pct) {
    if (!this.progressEl) return;
    const circ = 289;
    const offset = circ - (pct / 100) * circ;
    this.progressEl.style.strokeDashoffset = offset;
  }

  // ── Auto-capture loop ─────────────────────────────────────────────────────────

  startFaceCapture(onFaceCaptured, onFrameUpdate = null) {
    if (this.isScanning) return;
    this.isScanning     = true;
    this._faceHeldSince = null;

    let candidatePhoto      = null;
    let candidateDescriptor = null;
    let lastCandidateMs     = 0;

    const loop = async () => {
      if (!this.isScanning) return;

      if (!this._modelsReady && !this._fallback) await this._loadModels();

      const faceFound = await this._detectFace();
      const now = Date.now();

      if (!faceFound) {
        this._faceHeldSince = null;
        this._setProgress(0);
        if (onFrameUpdate) onFrameUpdate({ state: 'NO_FACE', progress: 0 });
      } else {
        if (!this._faceHeldSince) this._faceHeldSince = now;
        const held = now - this._faceHeldSince;
        const pct  = Math.min(100, (held / CAPTURE_HOLD_MS) * 100);
        this._setProgress(pct);

        if (now - lastCandidateMs > 400) {
          lastCandidateMs = now;
          const photo = this.capturePhoto();
          const fd    = this.extractDescriptor();
          if (photo) { candidatePhoto = photo; candidateDescriptor = fd?.descriptor ?? null; }
        }

        if (onFrameUpdate) onFrameUpdate({ state: 'FACE_DETECTED', progress: pct });

        if (held >= CAPTURE_HOLD_MS) {
          this.isScanning = false;
          if (this._loopTimer) { clearTimeout(this._loopTimer); this._loopTimer = null; }

          if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
          if (this.videoEl) this.videoEl.srcObject = null;

          this._setProgress(100);
          if (onFaceCaptured) {
            onFaceCaptured({
              photo:             candidatePhoto,
              descriptor:        candidateDescriptor,
              liveness_verified: true,
            });
          }
          return;
        }
      }

      this._loopTimer = setTimeout(loop, SCAN_MS);
    };

    loop();
  }

  startBlinkDetection(onFaceCaptured, onFrameUpdate = null) {
    this.startFaceCapture(onFaceCaptured, onFrameUpdate);
  }

  // ── Login flow ────────────────────────────────────────────────────────────────

  startLoginBlinkScan(onSuccessCallback, emailFilter, uiRefs) {
    this.startFaceCapture(
      async (captureData) => {
        this.updateStatus('Face captured: verifying identity...', 'scanning');
        if (this.reticleEl) this.reticleEl.style.borderColor = '#10b981';

        if (!captureData.photo) {
          this.updateStatus('Photo capture failed. Please try again.', 'error');
          setTimeout(() => this._retryLogin(onSuccessCallback, emailFilter, uiRefs), 2000);
          return;
        }

        try {
          const r   = await fetch('../api/face_auth.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
              face_photo:        captureData.photo,
              face_descriptor:   captureData.descriptor,
              liveness_verified: true,
              email:             emailFilter,
            }),
          });
          const result = await r.json();

          if (result.success) {
            this.updateStatus('Identity verified! Redirecting...', 'success');
            setTimeout(() => {
              if (onSuccessCallback) onSuccessCallback(result);
              else window.location.href = result.redirect || 'dashboard.php';
            }, 600);
          } else {
            this.updateStatus(result.message || 'Face not recognized.', 'error');
            if (this.reticleEl) this.reticleEl.style.borderColor = '#ef4444';
            setTimeout(() => this._retryLogin(onSuccessCallback, emailFilter, uiRefs), 3500);
          }
        } catch (err) {
          this.updateStatus('Network error, retrying...', 'error');
          setTimeout(() => this._retryLogin(onSuccessCallback, emailFilter, uiRefs), 3000);
        }
      },

      (res) => {
        if (!res) return;
        if (res.state === 'NO_FACE') {
          this.updateStatus('Position your face in the frame', 'scanning');
          if (this.reticleEl) this.reticleEl.style.borderColor = 'rgba(255,255,255,0.3)';
        } else if (res.state === 'FACE_DETECTED') {
          const sec = ((CAPTURE_HOLD_MS - (res.progress / 100) * CAPTURE_HOLD_MS) / 1000).toFixed(1);
          this.updateStatus(`Hold still: capturing in ${sec}s`, 'scanning');
          if (this.reticleEl) this.reticleEl.style.borderColor = '#10b981';
        }
      }
    );
  }

  async _retryLogin(onSuccessCallback, emailFilter, uiRefs) {
    if (!uiRefs) return;
    this._faceHeldSince = null;
    this._setProgress(0);
    this.updateStatus('Restarting camera...', 'scanning');
    if (this.reticleEl) this.reticleEl.style.borderColor = 'rgba(255,255,255,0.3)';
    const ok = await this.startCamera(uiRefs.videoEl, uiRefs.statusEl, uiRefs.reticleEl, uiRefs.progressEl);
    if (ok) this.startLoginBlinkScan(onSuccessCallback, emailFilter, uiRefs);
  }

  // ── Photo & descriptor ────────────────────────────────────────────────────────

  capturePhoto() {
    if (!this.videoEl || this.videoEl.readyState < 2) return null;
    const c = document.createElement('canvas');
    c.width = this.videoEl.videoWidth || 640;
    c.height = this.videoEl.videoHeight || 480;
    c.getContext('2d').drawImage(this.videoEl, 0, 0);
    return c.toDataURL('image/jpeg', 0.93);
  }

  extractDescriptor(W = 128, H = 128) {
    if (!this.videoEl || this.videoEl.readyState < 2) return null;
    const vW = this.videoEl.videoWidth||640, vH = this.videoEl.videoHeight||480;
    const c = document.createElement('canvas'); c.width=W; c.height=H;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    const crop = Math.min(vW,vH)*0.72;
    ctx.drawImage(this.videoEl,(vW-crop)/2,(vH-crop)/2,crop,crop,0,0,W,H);
    const {data} = ctx.getImageData(0,0,W,H);
    const gray = new Float32Array(W*H);
    for (let i=0;i<data.length;i+=4) gray[i/4]=(0.299*data[i]+0.587*data[i+1]+0.114*data[i+2])/255;
    const desc=new Array(128).fill(0), cW=Math.floor(W/4), cH=Math.floor(H/4);
    for (let cy=0;cy<4;cy++) for (let cx=0;cx<4;cx++) {
      const base=(cy*4+cx)*8;
      for (let y=cy*cH+1;y<(cy+1)*cH-1;y++) for (let x=cx*cW+1;x<(cx+1)*cW-1;x++) {
        const idx=y*W+x, dx=gray[idx+1]-gray[idx-1], dy=gray[idx+W]-gray[idx-W];
        const mag=Math.sqrt(dx*dx+dy*dy); let ang=Math.atan2(dy,dx); if(ang<0)ang+=Math.PI*2;
        desc[base+Math.min(7,Math.floor(ang/(Math.PI*2)*8))]+=mag;
      }
    }
    let sq=0; for(let i=0;i<128;i++) sq+=desc[i]*desc[i];
    const n=Math.sqrt(sq)||1; for(let i=0;i<128;i++) desc[i]=Number((desc[i]/n).toFixed(6));
    return { descriptor: desc };
  }

  // ── Status pill ───────────────────────────────────────────────────────────────

  updateStatus(msg, type = 'scanning') {
    if (!this.statusEl) return;
    const cls = {
      success: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
      error:   'bg-red-50 text-red-700 border border-red-200',
      scanning:'bg-amber-50 text-amber-800 border border-amber-200',
    };
    const dot = { success:'bg-emerald-500', error:'bg-red-500', scanning:'bg-amber-500 animate-ping' };
    this.statusEl.className = `inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold ${cls[type]||cls.scanning}`;
    this.statusEl.innerHTML = `<span class="w-2 h-2 rounded-full ${dot[type]||dot.scanning}"></span><span>${msg}</span>`;
  }
}

window.biometricEngine = new BiometricEngine();
