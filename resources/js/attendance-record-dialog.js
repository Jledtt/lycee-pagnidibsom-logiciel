const dialog = document.querySelector('[data-attendance-record-dialog]');

if (dialog instanceof HTMLDialogElement) {
    const student = dialog.querySelector('[data-attendance-student]');
    const date = dialog.querySelector('[data-attendance-date]');
    const status = dialog.querySelector('[data-attendance-status]');
    const reason = dialog.querySelector('[data-attendance-reason]');
    const justifyForm = dialog.querySelector('[data-attendance-justify-form]');
    const clearForm = dialog.querySelector('[data-attendance-clear-form]');

    dialog.addEventListener('dialog:opening', (event) => {
        const trigger = event.detail?.trigger;

        if (! (trigger instanceof HTMLElement)) {
            return;
        }

        if (student) student.textContent = trigger.dataset.attendanceStudent || '-';
        if (date) date.textContent = trigger.dataset.attendanceDate || '-';
        if (status) status.textContent = trigger.dataset.attendanceStatus || '-';
        if (reason instanceof HTMLTextAreaElement) reason.value = trigger.dataset.attendanceReason || '';
        if (justifyForm instanceof HTMLFormElement) justifyForm.action = trigger.dataset.attendanceJustifyUrl || '#';
        if (clearForm instanceof HTMLFormElement) clearForm.action = trigger.dataset.attendanceClearUrl || '#';
    });
}
