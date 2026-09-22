const initializeTimetableAssignments = () => {
    document.querySelectorAll('[data-timetable-assignment]').forEach((select) => {
        const fields = select.closest('.timetable-cell-fields');
        const subject = fields?.querySelector('[data-timetable-subject]');
        const teacher = fields?.querySelector('[data-timetable-teacher]');
        const room = fields?.querySelector('[data-timetable-room]');
        const clearButton = fields?.querySelector('[data-timetable-clear]');

        if (!fields || !subject || !teacher || !room) {
            return;
        }

        const hasContent = () => Boolean(
            select.value
            || subject.value.trim()
            || teacher.value.trim()
            || room.value.trim()
        );

        const refreshClearButton = () => {
            if (clearButton) {
                clearButton.disabled = !hasContent();
            }
        };

        const synchronize = (replaceValues) => {
            const option = select.selectedOptions[0];
            const linked = Boolean(select.value);

            fields.classList.toggle('has-assignment', linked);

            if (linked && replaceValues) {
                subject.value = option?.dataset.subject || '';
                teacher.value = option?.dataset.teacher || '';
            }

            refreshClearButton();
        };

        select.addEventListener('change', () => synchronize(true));
        [subject, teacher, room].forEach((input) => {
            input.addEventListener('input', refreshClearButton);
        });

        clearButton?.addEventListener('click', () => {
            const confirmed = window.confirm(
                'Vider ce créneau ? La matière, le professeur et la salle seront effacés après enregistrement.',
            );

            if (!confirmed) {
                return;
            }

            select.value = '';
            subject.value = '';
            teacher.value = '';
            room.value = '';
            fields.classList.remove('has-assignment');
            fields.querySelector('[data-timetable-automatic-badge]')?.remove();
            refreshClearButton();
            subject.focus();
        });

        synchronize(Boolean(select.value));
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTimetableAssignments, { once: true });
} else {
    initializeTimetableAssignments();
}
