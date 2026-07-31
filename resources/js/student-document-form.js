function initializeStudentDocumentForm(form) {
    const dialog = form.closest('dialog');
    const nameInput = form.querySelector('[data-document-name]');
    const typeSelect = form.querySelector('[data-document-type]');
    const statusSelect = form.querySelector('[data-document-status]');
    const fileInput = form.querySelector('[data-document-file]');

    if (! nameInput || ! typeSelect || ! statusSelect || ! fileInput) {
        return;
    }

    const syncFileRequirement = () => {
        const fileRequired = statusSelect.value !== 'missing';
        fileInput.required = fileRequired;
        fileInput.closest('.field')?.classList.toggle('is-optional', ! fileRequired);
    };

    statusSelect.addEventListener('change', syncFileRequirement);

    dialog?.addEventListener('dialog:opening', (event) => {
        const trigger = event.detail?.trigger;

        if (! (trigger instanceof HTMLElement)) {
            return;
        }

        if (trigger.dataset.documentType) {
            typeSelect.value = trigger.dataset.documentType;
        }

        if (trigger.dataset.documentName) {
            nameInput.value = trigger.dataset.documentName;
        }

        syncFileRequirement();
    });

    syncFileRequirement();
}

document.querySelectorAll('[data-student-document-form]').forEach(initializeStudentDocumentForm);
