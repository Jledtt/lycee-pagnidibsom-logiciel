const statuses = ['unavailable', 'available', 'preferred'];
const labels = {
    unavailable: 'Indisponible',
    available: 'Disponible',
    preferred: 'Préféré',
};

const initializeTeacherAvailability = () => {
    const form = document.querySelector('[data-availability-form]');

    if (!form) return;

    const updateSummary = () => {
        const counts = { unavailable: 0, available: 0, preferred: 0 };

        form.querySelectorAll('[data-availability-input]').forEach((input) => {
            counts[input.value] = (counts[input.value] || 0) + 1;
        });

        Object.entries(counts).forEach(([status, count]) => {
            const target = form.querySelector(`[data-availability-count="${status}"]`);
            if (target) target.textContent = String(count);
        });
    };

    const setStatus = (button, status) => {
        if (!button || button.disabled || !statuses.includes(status)) return;

        const input = button.previousElementSibling;
        if (!(input instanceof HTMLInputElement)) return;

        statuses.forEach((value) => button.classList.remove(`availability-slot--${value}`));
        button.classList.add(`availability-slot--${status}`);
        button.dataset.status = status;
        input.value = status;

        const dot = button.querySelector('.availability-dot');
        if (dot) {
            statuses.forEach((value) => dot.classList.remove(`availability-dot--${value}`));
            dot.classList.add(`availability-dot--${status}`);
        }

        const label = button.querySelector('[data-availability-label]');
        if (label) label.textContent = labels[status];

        const context = (button.getAttribute('aria-label') || '').split(' : ')[0];
        button.setAttribute('aria-label', `${context} : ${labels[status]}`);
    };

    form.querySelectorAll('[data-availability-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const currentIndex = statuses.indexOf(button.dataset.status || 'unavailable');
            setStatus(button, statuses[(currentIndex + 1) % statuses.length]);
            updateSummary();
        });
    });

    form.querySelectorAll('[data-availability-all]').forEach((button) => {
        button.addEventListener('click', () => {
            form.querySelectorAll('[data-availability-toggle]').forEach((slot) => {
                setStatus(slot, button.dataset.availabilityAll);
            });
            updateSummary();
        });
    });

    form.querySelectorAll('[data-availability-day]').forEach((button) => {
        button.addEventListener('click', () => {
            const day = button.dataset.availabilityDay;
            form.querySelectorAll(`[data-availability-input][data-day="${day}"]`).forEach((input) => {
                setStatus(input.nextElementSibling, 'available');
            });
            updateSummary();
        });
    });

    updateSummary();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTeacherAvailability, { once: true });
} else {
    initializeTeacherAvailability();
}
