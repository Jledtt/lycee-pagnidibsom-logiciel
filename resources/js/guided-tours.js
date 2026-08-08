const configurationElement = document.getElementById('lpp-guided-tours-config');
let configuration = null;

try {
    configuration = configurationElement
        ? JSON.parse(configurationElement.textContent ?? '{}')
        : null;
} catch {
    configuration = null;
}

window.LPP_GUIDED_TOURS = configuration;

if (configuration?.userId && Array.isArray(configuration.tours)) {
    const deferredDuration = 7 * 24 * 60 * 60 * 1000;
    const memoryState = new Map();
    const tours = new Map(configuration.tours.map((tour) => [tour.key, tour]));
    let activeTour = null;
    let invitation = null;
    let scheduledPosition = null;

    const stateKey = (tourKey) => `lpp:guided-tour:${configuration.userId}:${tourKey}`;

    const readState = (tourKey) => {
        try {
            const saved = window.localStorage.getItem(stateKey(tourKey));

            return saved ? JSON.parse(saved) : {};
        } catch {
            return memoryState.get(tourKey) ?? {};
        }
    };

    const writeState = (tourKey, state) => {
        const nextState = {
            ...readState(tourKey),
            ...state,
            updatedAt: new Date().toISOString(),
        };

        memoryState.set(tourKey, nextState);

        try {
            window.localStorage.setItem(stateKey(tourKey), JSON.stringify(nextState));
        } catch {
            // The in-memory state keeps the tour usable when storage is unavailable.
        }

        document.dispatchEvent(new CustomEvent('guided-tour:state-changed', {
            detail: { tourKey, state: nextState },
        }));

        return nextState;
    };

    const removeState = (tourKey) => {
        memoryState.delete(tourKey);

        try {
            window.localStorage.removeItem(stateKey(tourKey));
        } catch {
            // Ignore unavailable browser storage.
        }

        document.dispatchEvent(new CustomEvent('guided-tour:state-changed', {
            detail: { tourKey, state: {} },
        }));
    };

    const createElement = (tagName, className, text = '') => {
        const element = document.createElement(tagName);
        element.className = className;

        if (text !== '') {
            element.textContent = text;
        }

        return element;
    };

    const findTarget = (targetName) => document.querySelector(`[data-tour-target="${targetName}"]`);

    const availableSteps = (tour) => tour.steps
        .map((step) => ({ ...step, element: findTarget(step.target) }))
        .filter((step) => step.element instanceof HTMLElement && step.element.offsetParent !== null);

    const closeInvitation = () => {
        invitation?.remove();
        invitation = null;
    };

    const updateDocumentationCards = () => {
        document.querySelectorAll('[data-guided-tour-card]').forEach((card) => {
            const tourKey = card.dataset.guidedTourCard;
            const state = readState(tourKey);
            const status = card.querySelector('[data-guided-tour-status]');
            const action = card.querySelector('[data-guided-tour-action]');

            if (status) {
                status.textContent = state.status === 'completed'
                    ? 'Terminée'
                    : state.status === 'ignored'
                        ? 'Ignorée'
                        : state.status === 'started'
                            ? 'À terminer'
                            : 'À découvrir';
                status.dataset.status = state.status ?? 'new';
            }

            if (action) {
                action.textContent = state.status === 'completed' ? 'Revoir' : 'Démarrer';
            }
        });
    };

    const focusableElements = (container) => Array.from(container.querySelectorAll(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ));

    const positionCurrentStep = () => {
        if (! activeTour) {
            return;
        }

        const { card, highlight, steps, index } = activeTour;
        const target = steps[index]?.element;

        if (! target?.isConnected) {
            showStep(index + 1);
            return;
        }

        const targetRect = target.getBoundingClientRect();
        const padding = 6;
        const viewportMargin = 16;

        highlight.style.left = `${Math.max(0, targetRect.left - padding)}px`;
        highlight.style.top = `${Math.max(0, targetRect.top - padding)}px`;
        highlight.style.width = `${Math.min(window.innerWidth, targetRect.width + padding * 2)}px`;
        highlight.style.height = `${Math.min(window.innerHeight, targetRect.height + padding * 2)}px`;

        if (window.innerWidth <= 720) {
            card.style.left = `${viewportMargin}px`;
            card.style.right = `${viewportMargin}px`;
            card.style.top = 'auto';
            card.style.bottom = `${viewportMargin}px`;
            card.style.width = 'auto';
            return;
        }

        card.style.width = `${Math.min(380, window.innerWidth - viewportMargin * 2)}px`;
        card.style.right = 'auto';
        card.style.bottom = 'auto';

        const cardRect = card.getBoundingClientRect();
        let top = targetRect.bottom + 14;

        if (top + cardRect.height > window.innerHeight - viewportMargin) {
            top = targetRect.top - cardRect.height - 14;
        }

        if (top < viewportMargin) {
            top = Math.min(
                window.innerHeight - cardRect.height - viewportMargin,
                Math.max(viewportMargin, targetRect.top),
            );
        }

        let left = targetRect.left;

        if (left + cardRect.width > window.innerWidth - viewportMargin) {
            left = window.innerWidth - cardRect.width - viewportMargin;
        }

        card.style.left = `${Math.max(viewportMargin, left)}px`;
        card.style.top = `${Math.max(viewportMargin, top)}px`;
    };

    const schedulePosition = () => {
        if (scheduledPosition !== null) {
            window.cancelAnimationFrame(scheduledPosition);
        }

        scheduledPosition = window.requestAnimationFrame(() => {
            scheduledPosition = null;
            positionCurrentStep();
        });
    };

    const finishTour = (status = 'completed') => {
        if (! activeTour) {
            return;
        }

        const { tour, layer, previousFocus } = activeTour;
        writeState(tour.key, {
            status,
            completedAt: status === 'completed' ? new Date().toISOString() : null,
        });

        layer.remove();
        activeTour = null;
        document.documentElement.classList.remove('has-guided-tour');
        window.removeEventListener('resize', schedulePosition);
        window.removeEventListener('scroll', schedulePosition, true);

        if (previousFocus?.isConnected) {
            previousFocus.focus({ preventScroll: true });
        }
    };

    function showStep(requestedIndex) {
        if (! activeTour) {
            return;
        }

        const { steps, card, highlight } = activeTour;
        const index = Math.max(0, Math.min(requestedIndex, steps.length - 1));
        const step = steps[index];

        if (! step?.element?.isConnected) {
            if (index < steps.length - 1) {
                showStep(index + 1);
            } else {
                finishTour('completed');
            }
            return;
        }

        activeTour.index = index;
        card.querySelector('[data-guided-tour-title]').textContent = step.title;
        card.querySelector('[data-guided-tour-description]').textContent = step.description;
        card.querySelector('[data-guided-tour-progress]').textContent = `${index + 1} sur ${steps.length}`;

        const previousButton = card.querySelector('[data-guided-tour-previous]');
        const nextButton = card.querySelector('[data-guided-tour-next]');
        previousButton.disabled = index === 0;
        nextButton.textContent = index === steps.length - 1 ? 'Terminer' : 'Suivant';

        highlight.setAttribute('aria-label', step.title);
        const mobileLayout = window.innerWidth <= 720;

        step.element.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: mobileLayout ? 'start' : 'center',
            inline: 'nearest',
        });

        window.setTimeout(schedulePosition, 180);
        nextButton.focus({ preventScroll: true });
    }

    const buildTourLayer = (tour, steps) => {
        const layer = createElement('div', 'guided-tour-layer');
        const highlight = createElement('div', 'guided-tour-highlight');
        const card = createElement('section', 'guided-tour-card');
        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-modal', 'true');
        card.setAttribute('aria-labelledby', 'guided-tour-title');
        card.setAttribute('aria-describedby', 'guided-tour-description');

        const header = createElement('div', 'guided-tour-card__header');
        const progress = createElement('span', 'guided-tour-progress');
        progress.dataset.guidedTourProgress = '';
        const quitButton = createElement('button', 'guided-tour-quit', 'Quitter');
        quitButton.type = 'button';
        quitButton.dataset.guidedTourQuit = '';
        header.append(progress, quitButton);

        const title = createElement('h2', 'guided-tour-card__title');
        title.id = 'guided-tour-title';
        title.dataset.guidedTourTitle = '';
        const description = createElement('p', 'guided-tour-card__description');
        description.id = 'guided-tour-description';
        description.dataset.guidedTourDescription = '';

        const footer = createElement('div', 'guided-tour-card__footer');
        const previousButton = createElement('button', 'btn btn-subtle', 'Précédent');
        previousButton.type = 'button';
        previousButton.dataset.guidedTourPrevious = '';
        const nextButton = createElement('button', 'btn btn-primary', 'Suivant');
        nextButton.type = 'button';
        nextButton.dataset.guidedTourNext = '';
        footer.append(previousButton, nextButton);

        card.append(header, title, description, footer);
        layer.append(highlight, card);

        previousButton.addEventListener('click', () => showStep(activeTour.index - 1));
        nextButton.addEventListener('click', () => {
            if (activeTour.index === steps.length - 1) {
                finishTour('completed');
            } else {
                showStep(activeTour.index + 1);
            }
        });
        quitButton.addEventListener('click', () => finishTour('started'));

        card.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = focusableElements(card);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        });

        return { layer, highlight, card };
    };

    const startTour = (tourKey) => {
        if (activeTour) {
            return false;
        }

        const tour = tours.get(tourKey);

        if (! tour || tour.route !== configuration.route) {
            return false;
        }

        const steps = availableSteps(tour);

        if (steps.length === 0) {
            return false;
        }

        closeInvitation();
        const elements = buildTourLayer(tour, steps);
        activeTour = {
            tour,
            steps,
            index: 0,
            previousFocus: document.activeElement instanceof HTMLElement ? document.activeElement : null,
            ...elements,
        };

        writeState(tour.key, {
            status: 'started',
            startedAt: readState(tour.key).startedAt ?? new Date().toISOString(),
        });

        document.body.append(elements.layer);
        document.documentElement.classList.add('has-guided-tour');
        window.addEventListener('resize', schedulePosition);
        window.addEventListener('scroll', schedulePosition, true);
        showStep(0);

        return true;
    };

    const showInvitation = (tour) => {
        if (invitation || activeTour) {
            return;
        }

        const state = readState(tour.key);
        const deferredUntil = state.deferredUntil ? Date.parse(state.deferredUntil) : 0;

        if (['completed', 'ignored'].includes(state.status) || deferredUntil > Date.now()) {
            return;
        }

        const sessionKey = `lpp:guided-tour-invitation:${configuration.userId}:${tour.key}`;

        try {
            if (window.sessionStorage.getItem(sessionKey)) {
                return;
            }
            window.sessionStorage.setItem(sessionKey, 'shown');
        } catch {
            // Showing the invitation once is still safe without session storage.
        }

        invitation = createElement('aside', 'guided-tour-invitation');
        invitation.setAttribute('role', 'region');
        invitation.setAttribute('aria-label', `Découvrir : ${tour.title}`);

        const kicker = createElement('span', 'guided-tour-invitation__kicker', 'Aide guidée');
        const title = createElement('strong', 'guided-tour-invitation__title', tour.title);
        const description = createElement('p', 'guided-tour-invitation__description', tour.description);
        const actions = createElement('div', 'guided-tour-invitation__actions');
        const startButton = createElement('button', 'btn btn-primary', 'Commencer la visite');
        const laterButton = createElement('button', 'btn btn-subtle', 'Plus tard');
        const ignoreButton = createElement('button', 'guided-tour-invitation__ignore', 'Ne plus afficher');
        startButton.type = laterButton.type = ignoreButton.type = 'button';
        actions.append(startButton, laterButton);
        invitation.append(kicker, title, description, actions, ignoreButton);

        startButton.addEventListener('click', () => startTour(tour.key));
        laterButton.addEventListener('click', () => {
            writeState(tour.key, {
                status: 'deferred',
                deferredUntil: new Date(Date.now() + deferredDuration).toISOString(),
            });
            closeInvitation();
        });
        ignoreButton.addEventListener('click', () => {
            writeState(tour.key, { status: 'ignored', deferredUntil: null });
            closeInvitation();
        });

        document.body.append(invitation);
    };

    document.addEventListener('keydown', (event) => {
        if (! activeTour) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            finishTour('started');
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            const nextButton = activeTour.card.querySelector('[data-guided-tour-next]');
            nextButton?.click();
        } else if (event.key === 'ArrowLeft' && activeTour.index > 0) {
            event.preventDefault();
            showStep(activeTour.index - 1);
        }
    });

    document.addEventListener('click', (event) => {
        if (! (event.target instanceof Element)) {
            return;
        }

        const resetButton = event.target.closest('[data-guided-tours-reset]');

        if (resetButton) {
            configuration.tours.forEach((tour) => removeState(tour.key));
            updateDocumentationCards();
        }
    });

    document.addEventListener('guided-tour:state-changed', updateDocumentationCards);
    updateDocumentationCards();

    const requestedTour = new URLSearchParams(window.location.search).get('tour');

    if (requestedTour && tours.has(requestedTour)) {
        window.setTimeout(() => startTour(requestedTour), 250);
    } else {
        const currentTour = configuration.tours.find(
            (tour) => tour.route === configuration.route && tour.autoPrompt,
        );

        if (currentTour) {
            window.setTimeout(() => showInvitation(currentTour), 650);
        }
    }

    window.LPPGuidedTours = {
        start: startTour,
        reset: removeState,
        state: readState,
    };
}
