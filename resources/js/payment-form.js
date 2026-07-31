function formatMoney(value) {
    return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value)} FCFA`;
}

function parseProfiles(form) {
    const source = form.querySelector('[data-payment-profiles]');

    try {
        return JSON.parse(source?.textContent || '{}');
    } catch {
        return {};
    }
}

function initializePaymentForm(form) {
    const profiles = parseProfiles(form);
    const studentSelect = form.querySelector('[data-payment-student]');
    const scheduleSelects = Array.from(form.querySelectorAll('[data-payment-schedule]'));
    const amountInputs = Array.from(form.querySelectorAll('[data-payment-amount]'));
    const total = form.querySelector('[data-payment-total]');

    if (! studentSelect || scheduleSelects.length === 0) {
        return;
    }

    const updateTotal = () => {
        const amount = amountInputs.reduce((sum, input) => sum + Math.max(Number(input.value || 0), 0), 0);
        if (total) {
            total.textContent = formatMoney(amount);
        }
    };

    const refreshDuplicateOptions = () => {
        const selectedValues = scheduleSelects.map((select) => select.value).filter(Boolean);

        scheduleSelects.forEach((select) => {
            Array.from(select.options).forEach((option) => {
                if (! option.value) {
                    return;
                }

                const alreadySelectedElsewhere = selectedValues.includes(option.value) && select.value !== option.value;
                option.disabled = Number(option.dataset.remaining || 0) <= 0 || alreadySelectedElsewhere;
            });
        });
    };

    const updateAmount = (select, replace = false) => {
        const line = select.closest('.payment-line');
        const input = line?.querySelector('[data-payment-amount]');
        const selected = select.options[select.selectedIndex];

        if (! input || ! selected?.dataset.remaining) {
            if (replace && input) {
                input.value = '';
            }
            updateTotal();
            return;
        }

        if (replace || ! input.value) {
            input.value = Math.max(Number(selected.dataset.remaining || 0), 0);
        }

        updateTotal();
    };

    const fillSchedules = () => {
        const schedules = profiles[studentSelect.value] || [];

        scheduleSelects.forEach((select) => {
            const preferredValue = select.dataset.oldValue || '';
            select.replaceChildren();

            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = schedules.length ? 'Choisir une tranche' : 'Aucune tranche configurée';
            select.appendChild(emptyOption);

            schedules.forEach((schedule) => {
                const option = document.createElement('option');
                option.value = schedule.id;
                option.dataset.remaining = schedule.remaining;
                option.textContent = `${schedule.label} - reste ${formatMoney(schedule.remaining)} sur ${formatMoney(schedule.amount)}`;
                option.disabled = schedule.remaining <= 0;
                option.selected = String(schedule.id) === String(preferredValue);
                select.appendChild(option);
            });

            delete select.dataset.oldValue;
            updateAmount(select);
        });

        refreshDuplicateOptions();
        updateTotal();
    };

    const applyTriggerPrefill = (trigger) => {
        if (! (trigger instanceof HTMLElement)) {
            return;
        }

        const studentId = trigger.dataset.paymentStudentId;
        const scheduleId = trigger.dataset.paymentScheduleId;
        const amount = trigger.dataset.paymentAmount;

        if (studentId) {
            studentSelect.value = studentId;
        }

        scheduleSelects.forEach((select, index) => {
            select.dataset.oldValue = index === 0 ? (scheduleId || '') : '';
        });
        amountInputs.forEach((input, index) => {
            input.value = index === 0 ? (amount || '') : '';
        });
        fillSchedules();
    };

    studentSelect.addEventListener('change', () => {
        scheduleSelects.forEach((select) => {
            select.dataset.oldValue = '';
        });
        amountInputs.forEach((input) => {
            input.value = '';
        });
        fillSchedules();
    });

    scheduleSelects.forEach((select) => {
        select.addEventListener('change', () => {
            updateAmount(select, true);
            refreshDuplicateOptions();
        });
    });
    amountInputs.forEach((input) => input.addEventListener('input', updateTotal));

    form.closest('dialog')?.addEventListener('dialog:opening', (event) => {
        applyTriggerPrefill(event.detail?.trigger);
    });

    fillSchedules();
}

document.querySelectorAll('[data-payment-form]').forEach(initializePaymentForm);
