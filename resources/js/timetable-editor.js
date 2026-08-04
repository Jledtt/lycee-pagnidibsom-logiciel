const initializeTimetableAssignments = () => {
    document.querySelectorAll('[data-timetable-assignment]').forEach((select) => {
        const fields = select.closest('.timetable-cell-fields');
        const subject = fields?.querySelector('[data-timetable-subject]');
        const teacher = fields?.querySelector('[data-timetable-teacher]');

        if (!fields || !subject || !teacher) {
            return;
        }

        const synchronize = (replaceValues) => {
            const option = select.selectedOptions[0];
            const linked = Boolean(select.value);

            fields.classList.toggle('has-assignment', linked);

            if (linked && replaceValues) {
                subject.value = option?.dataset.subject || '';
                teacher.value = option?.dataset.teacher || '';
            }
        };

        select.addEventListener('change', () => synchronize(true));
        synchronize(Boolean(select.value));
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTimetableAssignments, { once: true });
} else {
    initializeTimetableAssignments();
}
