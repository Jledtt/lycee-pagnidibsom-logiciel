const actionDialog = document.querySelector('[data-teacher-session-action-dialog]');

if (actionDialog instanceof HTMLDialogElement) {
    const teacher = actionDialog.querySelector('[data-teacher-session-teacher]');
    const course = actionDialog.querySelector('[data-teacher-session-course]');
    const time = actionDialog.querySelector('[data-teacher-session-time]');
    const hours = actionDialog.querySelector('[data-teacher-session-hours]');
    const validateForm = actionDialog.querySelector('[data-teacher-session-validate-form]');
    const deleteForm = actionDialog.querySelector('[data-teacher-session-delete-form]');
    const validateNote = actionDialog.querySelector('[data-teacher-session-validate-note]');
    const deleteNote = actionDialog.querySelector('[data-teacher-session-delete-note]');
    const validateCommand = actionDialog.querySelector('[data-teacher-session-validate-command]');
    const deleteCommand = actionDialog.querySelector('[data-teacher-session-delete-command]');

    actionDialog.addEventListener('dialog:opening', (event) => {
        const trigger = event.detail?.trigger;

        if (! (trigger instanceof HTMLElement)) {
            return;
        }

        const validateUrl = trigger.dataset.sessionValidateUrl || '';
        const deleteUrl = trigger.dataset.sessionDeleteUrl || '';

        if (teacher) teacher.textContent = trigger.dataset.sessionTeacher || '-';
        if (course) course.textContent = trigger.dataset.sessionCourse || '-';
        if (time) time.textContent = trigger.dataset.sessionTime || '-';
        if (hours) hours.textContent = trigger.dataset.sessionHours || '-';
        if (validateForm instanceof HTMLFormElement) validateForm.action = validateUrl || '#';
        if (deleteForm instanceof HTMLFormElement) deleteForm.action = deleteUrl || '#';
        if (validateNote instanceof HTMLElement) validateNote.hidden = ! validateUrl;
        if (deleteNote instanceof HTMLElement) deleteNote.hidden = ! deleteUrl;
        if (validateCommand instanceof HTMLButtonElement) validateCommand.hidden = ! validateUrl;
        if (deleteCommand instanceof HTMLButtonElement) deleteCommand.hidden = ! deleteUrl;
    });
}
