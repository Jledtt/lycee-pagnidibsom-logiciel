const activeTriggers = new WeakMap();

function getDialog(id) {
    const dialog = document.getElementById(id);

    if (typeof HTMLDialogElement === 'undefined') {
        return null;
    }

    return dialog instanceof HTMLDialogElement ? dialog : null;
}

function syncPageState() {
    const hasOpenDialog = document.querySelector('dialog[open]') !== null;
    document.documentElement.classList.toggle('has-open-dialog', hasOpenDialog);
}

function openDialog(dialog, trigger = null) {
    if (typeof dialog.showModal !== 'function') {
        return false;
    }

    document.querySelectorAll('dialog[open]').forEach((openDialogElement) => {
        if (openDialogElement !== dialog) {
            openDialogElement.close();
        }
    });

    if (trigger instanceof HTMLElement) {
        activeTriggers.set(dialog, trigger);
    }

    if (dialog.open) {
        dialog.close();
    }

    dialog.dispatchEvent(new CustomEvent('dialog:opening', {
        detail: { trigger },
    }));
    dialog.showModal();
    syncPageState();

    return true;
}

function closeDialog(dialog) {
    if (dialog.open) {
        dialog.close();
    }
}

document.addEventListener('click', (event) => {
    if (! (event.target instanceof Element)) {
        return;
    }

    const openTrigger = event.target.closest('[data-dialog-open]');

    if (openTrigger) {
        const dialog = getDialog(openTrigger.dataset.dialogOpen);

        if (dialog && openDialog(dialog, openTrigger)) {
            event.preventDefault();
        }

        return;
    }

    const closeTrigger = event.target.closest('[data-dialog-close]');

    if (closeTrigger) {
        const dialog = closeTrigger.closest('dialog');

        if (dialog instanceof HTMLDialogElement) {
            closeDialog(dialog);
        }
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (! (form instanceof HTMLFormElement) || ! form.matches('[data-prevent-double-submit]')) {
        return;
    }

    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = 'true';

    window.setTimeout(() => {
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((submitButton) => {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-disabled', 'true');

            if (submitButton instanceof HTMLButtonElement && submitButton.dataset.submittingLabel) {
                submitButton.dataset.originalLabel = submitButton.textContent.trim();
                submitButton.textContent = submitButton.dataset.submittingLabel;
            }
        });
    }, 0);
});

document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('cancel', (event) => {
        if (dialog.dataset.dismissible === 'false') {
            event.preventDefault();
        }
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog && dialog.dataset.dismissible !== 'false') {
            closeDialog(dialog);
        }
    });

    dialog.addEventListener('close', () => {
        syncPageState();

        const trigger = activeTriggers.get(dialog);
        if (trigger?.isConnected) {
            trigger.focus({ preventScroll: true });
        }
    });
});

document.querySelectorAll('dialog[data-dialog-open-on-load]').forEach((dialog) => {
    openDialog(dialog);
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-prevent-double-submit]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('[aria-disabled="true"]').forEach((submitButton) => {
            submitButton.disabled = false;
            submitButton.removeAttribute('aria-disabled');

            if (submitButton instanceof HTMLButtonElement && submitButton.dataset.originalLabel) {
                submitButton.textContent = submitButton.dataset.originalLabel;
                delete submitButton.dataset.originalLabel;
            }
        });
    });
});
