(function () {
    const meta = document.querySelector('meta[name="session-lifetime"]');
    const timerEl = document.getElementById('session-timer');
    if (!meta || !timerEl) return;

    const loginUrl = document.querySelector('meta[name="login-url"]')?.content || '/login';

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
            timerEl.classList.remove('text-slate-400');
            timerEl.classList.add('text-red-500');
        } else {
            timerEl.classList.remove('text-red-500');
            timerEl.classList.add('text-slate-400');
        }
    }

    updateDisplay();

    setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            window.location.href = loginUrl;
            return;
        }
        updateDisplay();
    }, 1000);
})();
