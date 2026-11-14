const SPINNER = '<span class="spinner-border" aria-hidden="true"></span>';

function lock(button) {
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    const label = button.querySelector('.btn-label') ?? button;
    button.dataset.originalLabel = label.innerHTML;
    label.innerHTML = `${SPINNER} ${button.dataset.loadingText ?? label.textContent}`;
}

function unlock(button) {
    button.disabled = false;
    button.removeAttribute('aria-busy');

    if (button.dataset.originalLabel !== undefined) {
        (button.querySelector('.btn-label') ?? button).innerHTML = button.dataset.originalLabel;
        delete button.dataset.originalLabel;
    }
}

/**
 * Stop double submissions: once a form is sent its submit buttons are disabled and show a spinner.
 * The buttons are locked after the submit event finishes, so the clicked button's value is still sent.
 */
export function initSubmit() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-lock')) {
            return;
        }

        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';
        setTimeout(() => {
            form.querySelectorAll('button[type="submit"]').forEach(lock);
        }, 0);
    });

    // Coming back with the browser's back button must not show a stuck spinner.
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }
        document.querySelectorAll('form[data-submitting]').forEach((form) => {
            delete form.dataset.submitting;
            form.querySelectorAll('button[type="submit"]').forEach(unlock);
        });
    });
}
