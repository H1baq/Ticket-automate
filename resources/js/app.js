import 'bootstrap/js/dist/dropdown';
import 'bootstrap/js/dist/modal';
import 'bootstrap/js/dist/offcanvas';

import { initCharCount } from './modules/char-count';
import { initConfirm } from './modules/confirm';
import { initCopy } from './modules/copy';
import { initShortcuts } from './modules/shortcuts';
import { initSubmit } from './modules/submit';
import { initToasts } from './modules/toasts';

document.documentElement.classList.add('js');

initToasts();
initConfirm();
initShortcuts();
initSubmit();
initCopy();
initCharCount();
