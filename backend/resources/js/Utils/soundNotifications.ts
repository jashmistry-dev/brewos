/**
 * High-Loudness, Browser-Safe Audio Notification Synthesizer for BrewOS Staff & Kitchen Displays.
 * Uses Web Audio API with AudioContext resume handling, DynamicsCompressor, and Multi-Harmonic Gain Staging
 * to produce crisp, loud, broadcast-quality attention chimes on laptop/desktop speakers.
 */

let globalAudioCtx: AudioContext | null = null;
let lastSoundPlayedAt = 0;

function getAudioContext(): AudioContext | null {
    if (typeof window === 'undefined') return null;

    if (!globalAudioCtx) {
        const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
        if (AudioContextClass) {
            globalAudioCtx = new AudioContextClass();
        }
    }

    if (globalAudioCtx && globalAudioCtx.state === 'suspended') {
        globalAudioCtx.resume().catch(() => {
            // Inhibited until explicit user gesture
        });
    }

    return globalAudioCtx;
}

export function unlockAudio(): boolean {
    const ctx = getAudioContext();
    if (!ctx) return false;

    try {
        if (ctx.state === 'suspended') {
            ctx.resume();
        }
        // Play an imperceptible silent test tone to permanently unlock browser audio context
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        gain.gain.setValueAtTime(0.001, ctx.currentTime);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.05);
        console.log('[Notification] AudioContext unlocked successfully');
        return true;
    } catch (e) {
        console.warn('[Notification] Audio unlock failed:', e);
        return false;
    }
}

/**
 * Plays a rich, multi-harmonic tone with smooth exponential decay.
 */
function playHarmonicTone(
    ctx: AudioContext,
    destination: AudioNode,
    freq: number,
    startTime: number,
    duration: number,
    volume: number = 0.6
) {
    // Fundamental tone (Sine wave)
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(freq, startTime);
    gain1.gain.setValueAtTime(volume, startTime);
    gain1.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);
    osc1.connect(gain1);
    gain1.connect(destination);
    osc1.start(startTime);
    osc1.stop(startTime + duration);

    // 2nd Harmonic Over-tone (Triangle wave for speaker clarity and body)
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'triangle';
    osc2.frequency.setValueAtTime(freq * 2, startTime);
    gain2.gain.setValueAtTime(volume * 0.25, startTime);
    gain2.gain.exponentialRampToValueAtTime(0.0001, startTime + duration * 0.7);
    osc2.connect(gain2);
    gain2.connect(destination);
    osc2.start(startTime);
    osc2.stop(startTime + duration * 0.7);
}

export function playNotificationSound(requestType: string = 'call_staff') {
    const nowMs = Date.now();
    // Debounce guard to prevent overlapping triggers within 800ms
    if (nowMs - lastSoundPlayedAt < 800) {
        console.log('[Notification] Sound playback debounced (duplicate trigger within 800ms)');
        return;
    }
    lastSoundPlayedAt = nowMs;

    const ctx = getAudioContext();
    if (!ctx || ctx.state !== 'running') {
        console.warn('[Notification] AudioContext is not running. Click "Enable Notification Sound" to unlock audio.');
        return;
    }

    const now = ctx.currentTime;

    try {
        // High-perceived-loudness pipeline: Oscillators -> DynamicsCompressor -> MasterGain -> Destination
        const compressor = ctx.createDynamicsCompressor();
        compressor.threshold.setValueAtTime(-18, now);
        compressor.knee.setValueAtTime(6, now);
        compressor.ratio.setValueAtTime(10, now);
        compressor.attack.setValueAtTime(0.002, now);
        compressor.release.setValueAtTime(0.2, now);

        const masterGain = ctx.createGain();
        masterGain.gain.setValueAtTime(0.95, now); // Maximum safe gain staging

        compressor.connect(masterGain);
        masterGain.connect(ctx.destination);

        if (requestType === 'water') {
            // Water Request: Distinct 2-tone smooth chime (G5 784Hz -> C6 1046Hz)
            console.log('[Notification] Playing WATER notification chime');
            playHarmonicTone(ctx, compressor, 783.99, now, 0.45, 0.6);
            playHarmonicTone(ctx, compressor, 1046.50, now + 0.15, 0.6, 0.7);
        } else if (requestType === 'request_bill') {
            // Final Bill Request: Distinct 3-tone ascending sequence (E5 659Hz -> G5 784Hz -> B5 987Hz)
            console.log('[Notification] Playing FINAL BILL notification chime');
            playHarmonicTone(ctx, compressor, 659.25, now, 0.4, 0.55);
            playHarmonicTone(ctx, compressor, 783.99, now + 0.14, 0.4, 0.6);
            playHarmonicTone(ctx, compressor, 987.77, now + 0.28, 0.6, 0.7);
        } else {
            // Call Staff / Default: Strong 3-tone attention chime (F5 698Hz -> A5 880Hz -> C6 1046Hz)
            console.log('[Notification] Playing CALL STAFF notification chime');
            playHarmonicTone(ctx, compressor, 698.46, now, 0.45, 0.65);
            playHarmonicTone(ctx, compressor, 880.00, now + 0.14, 0.45, 0.7);
            playHarmonicTone(ctx, compressor, 1046.50, now + 0.28, 0.75, 0.85);
        }
    } catch (e) {
        console.warn('[Notification] Error playing audio notification:', e);
    }
}
