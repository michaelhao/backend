function fadeAndRemove(el) {
    el.style.opacity = '0';
    el.style.transition = 'opacity 0.5s';
    setTimeout(() => el.remove(), 500);
}

export function useFlash() {
    const showFlash = (type, message) => {
        const el = document.createElement('div');
        el.className = `flash flash-${type} flash-message`;
        el.textContent = message;
        document.querySelector('.flash-area')?.prepend(el);
        setTimeout(() => fadeAndRemove(el), 5000);
    };
    const autoDismissFlashes = (selector = '.flash-message', delay = 5000) => {
        document.querySelectorAll(selector).forEach((el) => setTimeout(() => fadeAndRemove(el), delay));
    };
    return { showFlash, autoDismissFlashes };
}
