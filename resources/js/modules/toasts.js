import Toast from 'bootstrap/js/dist/toast';

const TYPES = ['info', 'success', 'error'];

/**
 * Show a toast built from the server-rendered template. Errors stay until dismissed;
 * the others hide themselves after a few seconds. The region is aria-live, so
 * screen readers announce each new toast.
 */
export function showToast(type, message) {
    const region = document.getElementById('toast-region');
    const template = document.getElementById('toast-template');
    if (!region || !template) {
        return;
    }

    const kind = TYPES.includes(type) ? type : 'info';
    const source = template.content.querySelector(`[data-toast-type="${kind}"]`);
    if (!source) {
        return;
    }

    const element = source.cloneNode(true);
    element.querySelector('.toast-message').textContent = message;
    region.append(element);
    element.addEventListener('hidden.bs.toast', () => element.remove());
    new Toast(element).show();
}

/**
 * Buttons can raise a toast without any script of their own:
 * data-show-toast="success" data-toast-message="Saved."
 */
export function initToasts() {
    document.querySelectorAll('[data-toast-autoshow]').forEach((element) => {
        new Toast(element).show();
    });

    document.addEventListener('click', (event) => {
        const trigger =
            event.target instanceof Element ? event.target.closest('[data-show-toast]') : null;
        if (trigger) {
            showToast(trigger.dataset.showToast, trigger.dataset.toastMessage ?? '');
        }
    });

    document.addEventListener('hdtms:toast', (event) => {
        showToast(event.detail?.type, String(event.detail?.message ?? ''));
    });
}
