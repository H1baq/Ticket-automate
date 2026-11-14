import { showToast } from './toasts';

const FEEDBACK_MS = 1500;

function copyWithSelection(text) {
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.append(field);
    field.select();
    const copied = document.execCommand('copy');
    field.remove();

    return copied;
}

async function writeToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return;
        } catch {
            // Fall through: the page may not have focus or permission.
        }
    }

    // Also covers plain-HTTP intranet installs, which have no async clipboard API.
    if (!copyWithSelection(text)) {
        throw new Error('Copy is not available');
    }
}

/**
 * Buttons with data-copy="HD-2026-00142" copy that text and say so in a toast.
 */
export function initCopy() {
    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-copy]') : null;
        if (!button) {
            return;
        }

        const text = button.dataset.copy;
        try {
            await writeToClipboard(text);
            button.dataset.copied = 'true';
            setTimeout(() => delete button.dataset.copied, FEEDBACK_MS);
            showToast('success', `Copied ${text}`);
        } catch {
            showToast('error', `Could not copy ${text}. Select it and press Ctrl+C instead.`);
        }
    });
}
