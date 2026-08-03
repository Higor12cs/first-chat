const tones = {
    message: [{ frequency: 880, at: 0, duration: 0.12 }],
    waiting: [
        { frequency: 660, at: 0, duration: 0.12 },
        { frequency: 990, at: 0.14, duration: 0.16 },
    ],
};

let context = null;

function audioContext() {
    const Constructor = window.AudioContext ?? window.webkitAudioContext;

    if (!Constructor) {
        return null;
    }

    return (context ??= new Constructor());
}

export function unlockAlerts() {
    audioContext()
        ?.resume()
        .catch(() => {});
}

function beep(target, { frequency, at, duration }) {
    const oscillator = target.createOscillator();
    const gain = target.createGain();

    oscillator.type = "sine";
    oscillator.frequency.value = frequency;

    const start = target.currentTime + at;

    gain.gain.setValueAtTime(0, start);
    gain.gain.linearRampToValueAtTime(0.18, start + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

    oscillator.connect(gain);
    gain.connect(target.destination);

    oscillator.start(start);
    oscillator.stop(start + duration + 0.02);
}

export function playAlert(name) {
    const target = audioContext();
    const parts = tones[name];

    if (!target || !parts) {
        return;
    }

    const ring = () => {
        try {
            parts.forEach((part) => beep(target, part));
        } catch {}
    };

    if (target.state === "running") {
        ring();

        return;
    }

    target
        .resume()
        .then(ring)
        .catch(() => {});
}
