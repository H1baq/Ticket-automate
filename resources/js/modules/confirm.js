/**
 * One confirm modal can serve many rows. A trigger button names what to confirm:
 *
 *   data-bs-toggle="modal" data-bs-target="#confirm"
 *   data-confirm-action="/admin/users/4/deactivate"
 *   data-confirm-message="Deactivate Wanjiku Kamau?"
 *
 * Focus starts on Cancel so a stray Enter key never confirms a destructive action.
 */
export function initConfirm() {
    document.querySelectorAll('[data-confirm-modal]').forEach((modal) => {
        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) {
                return;
            }

            const form = modal.querySelector('[data-confirm-form]');
            const message = modal.querySelector('[data-confirm-message]');

            if (trigger.dataset.confirmAction) {
                form.action = trigger.dataset.confirmAction;
            }
            if (trigger.dataset.confirmMessage) {
                message.textContent = trigger.dataset.confirmMessage;
            }
        });

        modal.addEventListener('shown.bs.modal', () => {
            modal.querySelector('[data-confirm-cancel]')?.focus();
        });
    });
}
