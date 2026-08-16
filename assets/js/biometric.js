/**
 * MT Data AI Biometric Face Engine
 * Real-time webcam capture, facial feature extraction, descriptor matching, and enrollment.
 */

class BiometricEngine {
  constructor() {
    this.stream = null;
    this.videoEl = null;
    this.canvasEl = null;
    this.statusEl = null;
    this.reticleEl = null;
    this.isScanning = false;
    this.scanInterval = null;
  }

  /**
   * Start webcam stream and attach to video element
   */
  async startCamera(videoElement, statusElement = null, reticleElement = null) {
    this.videoEl = videoElement;
    this.statusEl = statusElement;
    this.reticleEl = reticleElement;

    try {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error("Webcam access not supported in this browser.");
      }

      this.updateStatus("Requesting camera access...", "scanning");

      this.stream = await navigator.mediaDevices.getUserMedia({
        video: {
          width: { ideal: 640 },
          height: { ideal: 480 },
          facingMode: "user",
        },
        audio: false,
      });

      this.videoEl.srcObject = this.stream;
      await this.videoEl.play();

      this.updateStatus(
        "Camera ready. Align your face in the circle.",
        "scanning",
      );
      return true;
    } catch (err) {
      console.error("Camera Error:", err);
      this.updateStatus(
        "Camera error: " + (err.message || "Permission denied"),
        "error",
      );
      return false;
    }
  }

  /**
   * Stop active camera stream
   */
  stopCamera() {
    this.isScanning = false;
    if (this.scanInterval) {
      clearInterval(this.scanInterval);
      this.scanInterval = null;
    }
    if (this.stream) {
      this.stream.getTracks().forEach((track) => track.stop());
      this.stream = null;
    }
    if (this.videoEl) {
      this.videoEl.srcObject = null;
    }
  }

  /**
   * Extract 128-dimensional biometric descriptor from current video frame
   */
  extractFaceDescriptor(targetWidth = 128, targetHeight = 128) {
    if (!this.videoEl || this.videoEl.readyState < 2) {
      return null;
    }

    const vW = this.videoEl.videoWidth || 640;
    const vH = this.videoEl.videoHeight || 480;

    // Create offscreen canvas
    const canvas = document.createElement("canvas");
    canvas.width = targetWidth;
    canvas.height = targetHeight;
    const ctx = canvas.getContext("2d", { willReadFrequently: true });

    // Center square crop of face area
    const cropSize = Math.min(vW, vH) * 0.75;
    const cropX = (vW - cropSize) / 2;
    const cropY = (vH - cropSize) / 2;

    ctx.drawImage(
      this.videoEl,
      cropX,
      cropY,
      cropSize,
      cropSize,
      0,
      0,
      targetWidth,
      targetHeight,
    );

    // Get pixel data for feature extraction
    const imgData = ctx.getImageData(0, 0, targetWidth, targetHeight);
    const data = imgData.data;

    // Convert to grayscale matrix
    const gray = new Float32Array(targetWidth * targetHeight);
    for (let i = 0; i < data.length; i += 4) {
      // Luminance: 0.299 R + 0.587 G + 0.114 B
      gray[i / 4] =
        (0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]) / 255.0;
    }

    // Generate 128-D spatial gradient histogram descriptor (16 cells x 8 gradient bins)
    const descriptor = new Array(128).fill(0);
    const cellW = Math.floor(targetWidth / 4);
    const cellH = Math.floor(targetHeight / 4);

    for (let cy = 0; cy < 4; cy++) {
      for (let cx = 0; cx < 4; cx++) {
        const cellIndex = (cy * 4 + cx) * 8;

        for (let y = cy * cellH + 1; y < (cy + 1) * cellH - 1; y++) {
          for (let x = cx * cellW + 1; x < (cx + 1) * cellW - 1; x++) {
            const idx = y * targetWidth + x;
            const dx = gray[idx + 1] - gray[idx - 1];
            const dy = gray[idx + targetWidth] - gray[idx - targetWidth];

            const magnitude = Math.sqrt(dx * dx + dy * dy);
            let angle = Math.atan2(dy, dx);
            if (angle < 0) angle += Math.PI * 2;

            const bin = Math.min(7, Math.floor((angle / (Math.PI * 2)) * 8));
            descriptor[cellIndex + bin] += magnitude;
          }
        }
      }
    }

    // L2 Normalize descriptor vector to unit sphere
    let sumSq = 0;
    for (let i = 0; i < 128; i++) {
      sumSq += descriptor[i] * descriptor[i];
    }
    const norm = Math.sqrt(sumSq) || 1.0;
    for (let i = 0; i < 128; i++) {
      descriptor[i] = Number((descriptor[i] / norm).toFixed(6));
    }

    // Generate a crisp thumbnail for avatar
    const thumbCanvas = document.createElement("canvas");
    thumbCanvas.width = 120;
    thumbCanvas.height = 120;
    const tCtx = thumbCanvas.getContext("2d");
    tCtx.drawImage(
      this.videoEl,
      cropX,
      cropY,
      cropSize,
      cropSize,
      0,
      0,
      120,
      120,
    );
    const thumbnail = thumbCanvas.toDataURL("image/jpeg", 0.85);

    return { descriptor, thumbnail };
  }

  /**
   * Start continuous biometric login scan
   */
  startLoginScan(onSuccessCallback, emailFilter = null) {
    if (this.isScanning) return;
    this.isScanning = true;

    let attemptCount = 0;
    const maxAttempts = 25; // Stop scanning after ~20 seconds of no match

    this.scanInterval = setInterval(async () => {
      if (!this.isScanning) return;
      attemptCount++;

      const faceData = this.extractFaceDescriptor();
      if (!faceData) return;

      // Visual feedback: Face detected
      if (this.reticleEl) {
        this.reticleEl.classList.add("detected");
      }
      this.updateStatus("Authenticating face...", "scanning");

      try {
        const response = await fetch("../api/face_auth.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            face_descriptor: faceData.descriptor,
            email: emailFilter,
          }),
        });

        const result = await response.json();

        if (result.success) {
          this.isScanning = false;
          clearInterval(this.scanInterval);
          this.updateStatus(
            `Welcome back, ${result.user.fullname}!`,
            "success",
          );

          if (this.reticleEl) {
            this.reticleEl.style.borderColor = "#10b981";
          }

          setTimeout(() => {
            if (onSuccessCallback) {
              onSuccessCallback(result);
            } else {
              window.location.href = result.redirect || "dashboard.php";
            }
          }, 1000);
        } else {
          if (attemptCount >= maxAttempts) {
            this.isScanning = false;
            clearInterval(this.scanInterval);
            this.updateStatus(
              "Face match timed out. Try again or use password.",
              "error",
            );
            if (this.reticleEl) this.reticleEl.classList.remove("detected");
          }
        }
      } catch (err) {
        console.error("Auth request failed:", err);
      }
    }, 800);
  }

  /**
   * Update status pill UI
   */
  updateStatus(message, type = "scanning") {
    if (!this.statusEl) return;
    this.statusEl.className = `scan-status-pill ${type}`;
    this.statusEl.innerHTML = `<span class="scan-status-dot"></span> <span>${message}</span>`;
  }
}

// Global instance
window.biometricEngine = new BiometricEngine();
