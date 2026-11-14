const CHORD_TIMEOUT_MS = 1000;

function isTyping(element) {
    return (
        element instanceof HTMLElement &&
        (element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName))
    );
}

function follow(name) {
    const link = document.querySelector(`a[data-shortcut="${name}"]`);
    if (link && link.getAttribute('href') !== '#') {
        window.location.assign(link.href);
    }
}

/**
 * Keyboard shortcuts: "n" new ticket, "/" search, "g" then "d" dashboard.
 * They are ignored while typing in a field, while a dialog is open and
 * whenever Ctrl, Cmd or Alt is held, so they never fight the browser.
 */
export function initShortcuts() {
    let waitingForSecondKey = false;
    let timer = null;

    document.addEventListener('keydown', (event) => {
        if (event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) {
            return;
        }
        if (isTyping(event.target) || document.body.classList.contains('modal-open')) {
            return;
        }

        const key = event.key.toLowerCase();

        if (waitingForSecondKey) {
            waitingForSecondKey = false;
            clearTimeout(timer);
            if (key === 'd') {
                event.preventDefault();
                follow('dashboard');
            }
            return;
        }

        if (key === '/') {
            const search = document.querySelector('[data-shortcut-search]');
            if (search) {
                event.preventDefault();
                search.focus();
                search.select();
            }
        } else if (key === 'n') {
            follow('new-ticket');
        } else if (key === 'g') {
            waitingForSecondKey = true;
            timer = setTimeout(() => {
                waitingForSecondKey = false;
            }, CHORD_TIMEOUT_MS);
        }
    });
}
