/**
 * Shows "12 / 150" beside a field label. The counter is decorative; the
 * field's maxlength is what actually enforces the limit.
 */
export function initCharCount() {
    document.querySelectorAll('[data-char-count-for]').forEach((counter) => {
        const field = document.getElementById(counter.dataset.charCountFor);
        if (!field) {
            return;
        }

        const update = () => {
            counter.textContent = `${field.value.length} / ${counter.dataset.max}`;
        };

        field.addEventListener('input', update);
        update();
    });
}
