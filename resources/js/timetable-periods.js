const initializeTimetablePeriods = () => {
    const trigger = document.querySelector('[data-add-timetable-period]');
    const template = document.querySelector('[data-timetable-period-template]');
    const body = template?.closest('form')?.querySelector('tbody');

    if (!trigger || !template || !body) {
        return;
    }

    trigger.addEventListener('click', () => {
        const index = body.querySelectorAll('tr').length;
        const orders = [...body.querySelectorAll('input[name$="[sort_order]"]')]
            .map((input) => Number.parseInt(input.value, 10) || 0);
        const order = Math.max(0, ...orders) + 1;
        const html = template.innerHTML
            .replaceAll('__INDEX__', String(index))
            .replaceAll('__ORDER__', String(order));

        body.insertAdjacentHTML('beforeend', html);
        body.lastElementChild?.querySelector('input[name$="[label]"]')?.focus();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTimetablePeriods, { once: true });
} else {
    initializeTimetablePeriods();
}
