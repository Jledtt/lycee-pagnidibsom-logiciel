import { expect, test } from '@playwright/test';

async function login(page, username = 'admin', password = 'Pagnidibsom') {
    await page.goto('/login');
    await page.getByLabel('Identifiant').fill(username);
    await page.getByLabel('Mot de passe').fill(password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

async function elementOverflowsViewport(locator) {
    return locator.evaluate((element) => {
        const bounds = element.getBoundingClientRect();

        return bounds.left < -1 || bounds.right > window.innerWidth + 1;
    });
}

test('la page de connexion est lisible et sans débordement horizontal', async ({ page }) => {
    const response = await page.goto('/login');

    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(/Connexion - Lycée Privé Pagnidibsom/);
    await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible();
    await expect(page.getByText('Accès sécurisé')).toBeVisible();

    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toMatch(/Ã|Â|â€™|�/);

    const hasHorizontalOverflow = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );
    expect(hasHorizontalOverflow).toBe(false);
});

test('un mauvais mot de passe est refusé', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Identifiant').fill('admin');
    await page.getByLabel('Mot de passe').fill('mot-de-passe-invalide');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('Identifiant ou mot de passe incorrect.')).toBeVisible();
});

test('un administrateur ouvre les modules principaux puis se déconnecte', async ({ page }) => {
    await login(page);
    await expect(page.locator('.topbar h1')).toHaveText('Tableau de bord');

    const modules = [
        ['/students', 'Élèves'],
        ['/payments', 'Paiements'],
        ['/attendance', 'Absences'],
        ['/communications', 'Notifications'],
    ];

    for (const [path, heading] of modules) {
        const response = await page.goto(path);

        expect(response?.status()).toBe(200);
        await expect(page.locator('.topbar h1')).toHaveText(heading);
        await expect(page).not.toHaveURL(/\/login$/);
    }

    await page.getByRole('button', { name: 'Déconnexion' }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible();
});

test('la navigation regroupe les modules et reste utilisable au clavier', async ({ page }, testInfo) => {
    await login(page);

    const navigation = page.getByRole('navigation', { name: 'Navigation principale' });
    const menuButton = page.locator('.sidebar-toggle');
    const compactNavigation = (page.viewportSize()?.width ?? 1280) <= 980;

    if (compactNavigation) {
        await expect(navigation).toBeHidden();
        await menuButton.click();
        await expect(navigation).toBeVisible();
        await expect(menuButton).toHaveAttribute('aria-expanded', 'true');
        await expect(menuButton).toHaveAttribute('aria-label', 'Masquer le menu');
    } else {
        await expect(navigation).toBeVisible();
    }

    const activeSection = navigation.locator('.nav-section.active-section');
    await expect(activeSection).toHaveCount(1);
    await expect(activeSection).toHaveAttribute('open', '');
    await expect(navigation.locator('a[aria-current="page"]')).toHaveText('Tableau de bord');

    const financeSection = navigation.locator('.nav-section').filter({ hasText: 'Finances' });
    expect(await financeSection.getAttribute('open')).toBeNull();
    await financeSection.locator('summary').click();
    await expect(financeSection).toHaveAttribute('open', '');
    await expect(financeSection.getByRole('link', { name: 'Paiements' })).toBeVisible();

    await expect(page.locator('.topbar__page-actions')).toBeVisible();
    await expect(page.locator('.topbar__account')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1)).toBe(false);

    const screenshotPath = testInfo.outputPath(`interface-shell-${testInfo.project.name}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: false });
    await testInfo.attach(`interface-shell-${testInfo.project.name}`, {
        path: screenshotPath,
        contentType: 'image/png',
    });

    if (compactNavigation) {
        await page.keyboard.press('Escape');
        await expect(navigation).toBeHidden();
        await expect(menuButton).toHaveAttribute('aria-expanded', 'false');
        await expect(menuButton).toBeFocused();
    }
});

test('les grands montants financiers restent dans leurs cartes', async ({ page }) => {
    await login(page);

    await page.locator('.finance-stats .money-amount').evaluateAll((amounts) => {
        amounts.forEach((amount) => {
            amount.textContent = '100 000 000';
        });
    });

    const overflowingCards = await page.locator('.finance-stats .stat').evaluateAll((cards) =>
        cards
            .map((card, index) => ({
                index,
                overflow: card.scrollWidth - card.clientWidth,
            }))
            .filter(({ overflow }) => overflow > 1),
    );
    const documentOverflows = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );

    expect(overflowingCards).toEqual([]);
    expect(documentOverflows).toBe(false);
    await expect(page.locator('.finance-stats .money-amount').first()).toHaveText('100 000 000');
    await expect(page.locator('.finance-stats .money-currency').first()).toHaveText('FCFA');
});

test('la planification automatique reste lisible et protège les grilles actives', async ({ page }, testInfo) => {
    await login(page);
    const response = await page.goto('/timetables/planning/automatic');

    expect(response?.status()).toBe(200);
    await expect(page.locator('.topbar h1')).toHaveText('Planification automatique');
    await expect(page.getByRole('list', { name: 'Étapes de planification' })).toBeVisible();
    await expect(page.getByRole('heading', { name: '1. Importer les disponibilités' })).toBeVisible();
    await expect(page.getByRole('heading', { name: '2. Générer une proposition' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Télécharger le modèle CSV' })).toBeVisible();
    await expect(page.getByText('Les classes avec un emploi du temps actif sont protégées et restent inchangées.')).toBeVisible();

    const hasHorizontalOverflow = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );
    expect(hasHorizontalOverflow).toBe(false);

    const screenshotPath = testInfo.outputPath(`planification-automatique-${testInfo.project.name}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true });
    await testInfo.attach(`planification-automatique-${testInfo.project.name}`, {
        path: screenshotPath,
        contentType: 'image/png',
    });
});

test('la fenêtre de paiement reste utilisable sur toutes les tailles d’écran', async ({ page }) => {
    await login(page);
    await page.goto('/payments');
    await page.getByRole('link', { name: 'Nouveau paiement' }).click();

    const dialog = page.locator('#payment-create-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Enregistrer le paiement' })).toBeVisible();

    const overflowsViewport = await dialog.evaluate((element) => {
        const bounds = element.getBoundingClientRect();

        return bounds.left < -1 || bounds.right > window.innerWidth + 1;
    });

    expect(overflowsViewport).toBe(false);

    await dialog.locator('.ui-dialog__close').click();
    await expect(dialog).toBeHidden();
});

test('les fenêtres du dossier élève restent utilisables sur toutes les tailles d’écran', async ({ page }) => {
    await login(page);
    await page.goto('/students?search=LPP-E2E-001');
    const studentRow = page.locator('table tbody tr').filter({ hasText: 'LPP-E2E-001' });
    await studentRow.getByRole('link', { name: 'Voir' }).click();

    await page.getByRole('link', { name: 'Ajouter une pièce' }).click();
    const documentDialog = page.locator('#student-document-dialog');
    await expect(documentDialog).toBeVisible();
    await expect(documentDialog.getByRole('button', { name: 'Ajouter au dossier' })).toBeVisible();
    expect(await elementOverflowsViewport(documentDialog)).toBe(false);
    await documentDialog.locator('.ui-dialog__close').click();

    await page.getByRole('link', { name: 'Résumé inscription' }).click();
    const enrollmentDrawer = page.locator('#student-enrollment-drawer');
    await expect(enrollmentDrawer).toBeVisible();
    expect(await elementOverflowsViewport(enrollmentDrawer)).toBe(false);
    await enrollmentDrawer.locator('.ui-drawer__close').click();

    await page.getByRole('link', { name: 'Situation financière' }).click();
    const financialDrawer = page.locator('#student-financial-drawer');
    await expect(financialDrawer).toBeVisible();
    expect(await elementOverflowsViewport(financialDrawer)).toBe(false);
});

test('la fenêtre de saisie des heures reste utilisable sur toutes les tailles d’écran', async ({ page }) => {
    await login(page);
    await page.goto('/teacher-work-sessions');
    await page.getByRole('button', { name: 'Ajouter des heures' }).click();

    const dialog = page.locator('#teacher-work-session-form-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Enregistrer les heures' })).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Annuler' })).toBeVisible();
    const sessionDate = dialog.locator('input[name="session_date"]');
    await expect(sessionDate).toHaveValue('2026-10-01');
    await expect(sessionDate).toHaveAttribute('min', '2026-10-01');
    await expect(sessionDate).toHaveAttribute('max', '2027-07-31');
    await expect(dialog.getByText('Dates autorisées : du 01/10/2026 au 31/07/2027.')).toBeVisible();
    expect(await elementOverflowsViewport(dialog)).toBe(false);
});

test('le résumé de l’emploi du temps reste lisible avec une équipe pédagogique longue', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/timetables');

    if (!await page.locator('.timetable-overview').isVisible()) {
        await page.getByRole('button', { name: 'Créer une grille vide' }).click();
        await expect(page).toHaveURL(/\/timetables\/\d+\/edit$/);

        await page.locator('input[name="title"]').fill('Emploi du temps provisoire');
        await page.locator('textarea[name="principal_teacher"]').fill([
            'Aminata Test (Français)',
            'Paul Exemple (Mathématiques)',
            'Mariam Démo (Anglais)',
            'Issa Essai (Histoire-Géographie)',
            'Awa Contrôle (EPS)',
            'Karim Validation (Physique-Chimie)',
            'Fatou Mobile (Allemand)',
            'Oumar Tablette (Philosophie)',
        ].join('; '));
        await page.getByRole('button', { name: 'Enregistrer l’emploi du temps' }).click();
        await page.getByRole('link', { name: 'Retour' }).click();
    }

    const overview = page.locator('.timetable-overview');
    await expect(overview).toBeVisible();
    await expect(overview.getByRole('heading', { name: 'E2E 5e A' })).toBeVisible();
    await expect(overview.getByRole('heading', { name: 'Équipe pédagogique' })).toBeVisible();
    await expect(overview.locator('.timetable-team__list li')).toHaveCount(8);
    expect(await elementOverflowsViewport(overview)).toBe(false);

    const screenshotPath = testInfo.outputPath(`timetable-overview-${testInfo.project.name}.png`);
    await overview.screenshot({ path: screenshotPath });
    await testInfo.attach(`timetable-overview-${testInfo.project.name}`, {
        path: screenshotPath,
        contentType: 'image/png',
    });
});

test('la configuration des créneaux reste utilisable sur toutes les tailles d’écran', async ({ page }) => {
    await login(page);
    await page.goto('/timetables/periods');

    await expect(page.getByRole('heading', { name: 'Créneaux des emplois du temps' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Enregistrer les créneaux' })).toBeVisible();

    const rows = page.locator('tbody tr');
    const initialCount = await rows.count();

    await page.getByRole('button', { name: 'Ajouter un créneau' }).click();
    await expect(rows).toHaveCount(initialCount + 1);

    const newRow = rows.last();
    await newRow.locator('input[name$="[label]"]').fill('17h00-18h00');
    await newRow.locator('input[name$="[starts_at]"]').fill('17:00');
    await newRow.locator('input[name$="[ends_at]"]').fill('18:00');
    await expect(newRow.locator('input[name$="[label]"]')).toHaveValue('17h00-18h00');
});

test('la saisie des disponibilités professeur reste claire et utilisable', async ({ page }) => {
    await login(page);
    await page.goto('/timetables/availabilities');

    await expect(page.getByRole('heading', { name: 'Disponibilités des professeurs' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Semaine habituelle' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Enregistrer en brouillon' })).toBeVisible();

    const form = page.locator('[data-availability-form]');
    const firstSlot = form.locator('[data-availability-toggle]').first();
    await expect(firstSlot).toContainText('Indisponible');
    await firstSlot.click();
    await expect(firstSlot).toContainText('Disponible');

    await form.getByRole('button', { name: 'Tout indisponible' }).click();
    await expect(form.locator('[data-availability-count="unavailable"]')).toHaveText('42');

    await form.locator('[data-availability-day="monday"]').click();
    await expect(form.locator('[data-availability-count="available"]')).toHaveText('7');
    expect(await elementOverflowsViewport(page.locator('.availability-overview'))).toBe(false);
});

test('les actions sensibles affichent leur objet et leurs conséquences', async ({ page }) => {
    await login(page);
    await page.goto('/students?search=LPP-E2E-001');
    const studentRow = page.locator('table tbody tr').filter({ hasText: 'LPP-E2E-001' });
    await studentRow.getByRole('link', { name: 'Voir' }).click();
    await page.getByRole('button', { name: 'Archiver le dossier' }).click();

    const dialog = page.locator('#app-confirmation-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('heading', { name: 'Archiver le dossier élève' })).toBeVisible();
    await expect(dialog.getByText(/Aminata Workflow.*LPP-E2E-001/)).toBeVisible();
    await expect(dialog.getByText(/historique scolaire, financier et administratif/)).toBeVisible();
    expect(await elementOverflowsViewport(dialog)).toBe(false);
    await dialog.getByRole('button', { name: 'Annuler' }).click();
    await expect(dialog).toBeHidden();

    await page.goto('/tariffs');
    await page.getByRole('button', { name: 'Initialiser affiche' }).click();
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('heading', { name: 'Initialiser les tarifs officiels' })).toBeVisible();
    await expect(dialog.getByText(/Toutes les classes actives/)).toBeVisible();
    await expect(dialog.getByText(/autres lignes personnalisées seront conservées/)).toBeVisible();
    await dialog.getByRole('button', { name: 'Initialiser les tarifs' }).click();
    await expect(page.getByText(/ligne\(s\) de tarifs initialisées/)).toBeVisible();
});

test('une confirmation se ferme avec Échap, restaure le focus et produit une capture', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/students?search=LPP-E2E-001');
    const studentRow = page.locator('table tbody tr').filter({ hasText: 'LPP-E2E-001' });
    await studentRow.getByRole('link', { name: 'Voir' }).click();

    const archiveButton = page.getByRole('button', { name: 'Archiver le dossier' });
    await archiveButton.click();
    const dialog = page.locator('#app-confirmation-dialog');
    await expect(dialog).toBeVisible();

    const screenshotPath = testInfo.outputPath(`confirmation-${testInfo.project.name}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: false });
    await testInfo.attach(`confirmation-${testInfo.project.name}`, {
        path: screenshotPath,
        contentType: 'image/png',
    });

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(archiveButton).toBeFocused();

    await archiveButton.click();
    await dialog.getByRole('button', { name: 'Annuler' }).click();
    await expect(dialog).toBeHidden();
});

test('la validation native bloque un paiement incomplet', async ({ page }) => {
    await login(page);
    await page.goto('/payments');
    await page.getByRole('link', { name: 'Nouveau paiement' }).click();

    const dialog = page.locator('#payment-create-dialog');
    const studentSelect = dialog.locator('[data-payment-student]');
    await dialog.getByRole('button', { name: 'Enregistrer le paiement' }).click();

    await expect(dialog).toBeVisible();
    expect(await studentSelect.evaluate((element) => element.checkValidity())).toBe(false);
    expect(await studentSelect.evaluate((element) => element.validationMessage)).not.toBe('');
    await expect(page).toHaveURL(/\/payments$/);
});

test('les permissions refusent les tarifs au secrétariat et autorisent les paiements au comptable', async ({ page }) => {
    await login(page, 'secretariat', 'secretariat');
    await expect(page.getByRole('link', { name: 'Tarifs' })).toHaveCount(0);
    const forbiddenResponse = await page.goto('/tariffs');
    expect(forbiddenResponse?.status()).toBe(403);

    await page.context().clearCookies();
    await login(page, 'comptable', 'comptable');
    const allowedResponse = await page.goto('/payments');
    expect(allowedResponse?.status()).toBe(200);
    await expect(page.getByRole('link', { name: 'Nouveau paiement' })).toBeVisible();
});

test('la documentation reste lisible et ouvre un guide', async ({ page }) => {
    await login(page);
    await page.goto('/help');

    await expect(page.locator('.topbar h1')).toHaveText('Documentation utilisateur');
    await expect(page.locator('.doc-topic').first()).toBeVisible();

    const documentOverflows = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );

    expect(documentOverflows).toBe(false);

    await page.locator('.doc-topic').first().click();
    await expect(page).toHaveURL(/\/help\/[a-z0-9-]+$/);
    await expect(page.getByRole('heading', { name: 'Étapes à suivre' })).toBeVisible();

    const guideOverflows = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );
    expect(guideOverflows).toBe(false);
});
