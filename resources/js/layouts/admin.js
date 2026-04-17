(function () {
    const meta = document.querySelector('meta[name="session-lifetime"]');
    const timerEl = document.getElementById('session-timer');
    if (!meta || !timerEl) return;

    let remaining = parseInt(meta.content, 10);

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function updateDisplay() {
        const h = Math.floor(remaining / 3600);
        const m = Math.floor((remaining % 3600) / 60);
        const s = remaining % 60;
        timerEl.textContent = `${pad(h)}:${pad(m)}:${pad(s)}`;

        if (remaining <= 300) {
            timerEl.classList.remove('text-gray-400');
            timerEl.classList.add('text-red-500');
        }
    }

    updateDisplay();

    setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            window.location.href = '/login';
            return;
        }
        updateDisplay();
    }, 1000);
})();
