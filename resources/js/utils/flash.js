const FLASH_COLORS = {
    success: 'bg-green-50 text-green-700',
    error: 'bg-red-50 text-red-700',
};

function fadeAndRemove(el) {
    el.style.opacity = '0';
    el.style.transition = 'opacity 0.5s';
    setTimeout(() => el.remove(), 500);
}

export function autoDismissFlashes(selector = '.flash-message', delay = 5000) {
    document.querySelectorAll(selector).forEach((el) => {
        setTimeout(() => fadeAndRemove(el), delay);
    });
}

export function showFlash(type, message) {
    const el = document.createElement('div');
    el.className = `mb-4 rounded-lg p-4 text-sm flash-message ${FLASH_COLORS[type] ?? ''}`;
    el.textContent = message;
    document.querySelector('.flash-area')?.prepend(el);
    setTimeout(() => fadeAndRemove(el), 5000);
}
