import { expect, test } from '@playwright/test';

const SEEDED_STUDENT = 'Aminata Workflow';
const SEEDED_MATRICULE = 'LPP-E2E-001';
const SEEDED_CLASS = 'E2E 5e A';

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Identifiant').fill('admin');
    await page.getByLabel('Mot de passe').fill('Pagnidibsom');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

async function selectOptionContaining(select, text) {
    const option = select.locator('option').filter({ hasText: text }).first();
    const value = await option.getAttribute('value');

    expect(value).toBeTruthy();
    await select.selectOption(value);

    return value;
}

test.beforeEach(async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium-desktop', 'Parcours métier exécutés une fois sur ordinateur.');
    await login(page);
});

test('création d’un élève puis inscription dans une classe', async ({ page }) => {
    await page.goto('/students/create');
    await page.locator('#last_name').fill('Navigateur');
    await page.locator('#first_name').fill('Nadine');
    await page.locator('#birth_date').fill('2012-05-20');
    await page.locator('#gender').selectOption('female');
    await page.locator('#father_last_name').fill('Navigateur');
    await page.locator('#father_first_name').fill('Issa');
    await page.locator('#father_phone_primary').fill('70000002');
    await page.locator('#father_email').fill('parent.navigateur@gmail.com');
    await page.getByRole('button', { name: 'Enregistrer le dossier' }).click();

    await expect(page).toHaveURL(/\/students\/\d+$/);
    await expect(page.locator('.topbar h1')).toHaveText('Nadine Navigateur');

    await page.goto('/enrollments/create');
    await selectOptionContaining(page.locator('#student_id'), 'Nadine Navigateur');
    await selectOptionContaining(page.locator('#school_class_id'), SEEDED_CLASS);
    await page.locator('#enrollment_date').fill('2026-10-02');
    await page.getByRole('button', { name: "Enregistrer l'inscription" }).click();

    await expect(page).toHaveURL(/\/enrollments\/\d+$/);
    await expect(page.getByText('Nadine Navigateur').first()).toBeVisible();
    await expect(page.getByText(SEEDED_CLASS).first()).toBeVisible();
});

test('paiement, reçu PDF puis annulation motivée', async ({ page }) => {
    await page.goto('/payments/create');
    await selectOptionContaining(page.locator('#student_id'), SEEDED_MATRICULE);
    await expect(page.locator('#lines_0_fee_schedule_id option')).toHaveCount(2);
    await page.locator('#lines_0_fee_schedule_id').selectOption({ index: 1 });
    await expect(page.locator('#lines_0_amount')).toHaveValue('25000');
    await page.locator('#paid_at').fill('2026-10-10T10:30');
    await page.getByRole('button', { name: 'Enregistrer le paiement' }).click();

    await expect(page).toHaveURL(/\/payments\/\d+$/);
    await expect(page.getByText('25 000 FCFA').first()).toBeVisible();

    const receiptHref = await page.getByRole('link', { name: 'Reçu PDF' }).getAttribute('href');
    const receipt = await page.request.get(receiptHref);
    expect(receipt.ok()).toBe(true);
    expect(receipt.headers()['content-type']).toContain('application/pdf');
    expect((await receipt.body()).byteLength).toBeGreaterThan(1000);

    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('#reason').fill('Annulation automatique du test navigateur');
    await page.getByRole('button', { name: 'Annuler le paiement' }).click();

    await expect(page.getByText('cancelled').first()).toBeVisible();
    await expect(page.getByText('Annulation automatique du test navigateur')).toBeVisible();
});

test('saisie des notes, verrouillage et génération du bulletin PDF', async ({ page }) => {
    await page.goto('/grades');
    const selection = page.locator('form.searchbar').first();
    await selectOptionContaining(selection.locator('select[name="school_class_id"]'), SEEDED_CLASS);
    await selection.locator('select[name="term_id"]').selectOption({ label: 'Trimestre 1' });
    await selection.locator('select[name="term_period_id"]').selectOption({ label: '1er devoir' });
    await selection.getByRole('button', { name: 'Afficher' }).click();

    const assessment = page.locator('input[name="title"]').locator('xpath=ancestor::form');
    await assessment.locator('select[name="subject_id"]').selectOption({ label: 'Français' });
    await assessment.locator('select[name="assessment_type_id"]').selectOption({ label: 'Devoir' });
    await assessment.locator('select[name="entry_mode"]').selectOption('standard');
    await assessment.locator('input[name="title"]').fill('Évaluation navigateur E2E');
    await assessment.locator('input[name="assessment_date"]').fill('2026-10-15');
    await assessment.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/assessment_id=\d+/);
    const studentRow = page.locator('table tbody tr').filter({ hasText: SEEDED_MATRICULE });
    await studentRow.locator('input[type="number"]').fill('15');
    await page.getByRole('button', { name: 'Enregistrer les notes' }).click();
    await expect(page.getByText('Notes enregistrées.')).toBeVisible();
    await page.getByRole('button', { name: 'Verrouiller' }).click();
    await expect(page.getByText('Évaluation verrouillée.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Déverrouiller' })).toBeVisible();

    await page.goto('/report-cards');
    const reportSelection = page.locator('form.searchbar').first();
    await selectOptionContaining(reportSelection.locator('select[name="school_class_id"]'), SEEDED_CLASS);
    await reportSelection.locator('select[name="term_id"]').selectOption({ label: 'Trimestre 1' });
    await reportSelection.getByRole('button', { name: 'Afficher' }).click();
    await page.getByRole('button', { name: 'Générer / recalculer' }).click();

    const reportRow = page.locator('table tbody tr').filter({ hasText: SEEDED_MATRICULE });
    await expect(reportRow).toContainText('15,00 / 20');
    const reportHref = await reportRow.getByRole('link', { name: 'PDF' }).getAttribute('href');
    const reportPdf = await page.request.get(reportHref);
    expect(reportPdf.ok()).toBe(true);
    expect(reportPdf.headers()['content-type']).toContain('application/pdf');
    expect((await reportPdf.body()).byteLength).toBeGreaterThan(1000);
});

test('absence enregistrée et email automatique présent dans l’historique', async ({ page }) => {
    await page.goto('/attendance');
    const selection = page.locator('form.searchbar').first();
    await selectOptionContaining(selection.locator('select[name="school_class_id"]'), SEEDED_CLASS);
    await selection.locator('input[name="date"]').fill('2026-10-16');
    await selection.getByRole('button', { name: 'Afficher' }).click();
    await page.getByRole('button', { name: /Faire l appel/ }).click();

    const studentRow = page.locator('table tbody tr').filter({ hasText: SEEDED_MATRICULE });
    await studentRow.locator('select[data-attendance-status]').selectOption('absent');
    await studentRow.locator('input[name$="[reason]"]').fill('Test navigateur');
    await page.getByRole('button', { name: 'Enregistrer le pointage' }).first().click();
    await expect(page.getByText('Pointage enregistré.')).toBeVisible();

    await page.goto('/communications?tab=history&search=parent.e2e@gmail.com');
    const messageRow = page.locator('table tbody tr')
        .filter({ hasText: 'parent.e2e@gmail.com' })
        .filter({ hasText: 'Absence / retard' });
    await expect(messageRow).toContainText('Absence / retard');
    await expect(messageRow).toContainText('Envoyé');
});

test('heures, ordre d’honoraires, paiement et dépense comptable', async ({ page }) => {
    await page.goto('/teacher-work-sessions');
    const workForm = page.locator('input[name="hours_worked"]').locator('xpath=ancestor::form');
    await workForm.locator('select[name="teacher_id"]').selectOption({ label: 'Enseignant' });
    await workForm.locator('input[name="session_date"]').fill('2026-10-17');
    await workForm.locator('select[name="school_class_id"]').selectOption({ label: SEEDED_CLASS });
    await workForm.locator('select[name="subject_id"]').selectOption({ label: 'Français' });
    await workForm.locator('input[name="starts_at"]').fill('08:00');
    await workForm.locator('input[name="ends_at"]').fill('11:00');
    await workForm.locator('input[name="hours_worked"]').fill('3');
    await workForm.locator('select[name="status"]').selectOption('validated');
    await workForm.getByRole('button', { name: 'Enregistrer les heures' }).click();
    await expect(page.getByText('Heures du professeur enregistrées.')).toBeVisible();

    await page.goto('/teacher-fees');
    const prepare = page.locator('form[action$="/teacher-fees/create"]');
    await prepare.locator('select[name="teacher_id"]').selectOption({ label: 'Enseignant' });
    await prepare.locator('input[name="month"]').fill('2026-10');
    await prepare.getByRole('button', { name: 'Préparer les honoraires' }).click();
    await expect(page.getByText('3,00').first()).toBeVisible();
    await page.getByRole('button', { name: /Créer l’ordre de paiement/ }).click();

    await expect(page).toHaveURL(/\/teacher-fees\/\d+$/);
    await expect(page.getByText('7 500 FCFA').first()).toBeVisible();
    await page.getByRole('button', { name: /Valider l’ordre de paiement/ }).click();
    await page.locator('input[name="paid_at"]').fill('2026-10-31');
    await page.locator('select[name="payment_method"]').selectOption({ label: 'Virement' });
    await page.locator('input[name="payment_reference"]').fill('E2E-VIR-001');
    await page.getByRole('button', { name: 'Marquer comme payé' }).click();
    await expect(page.getByText('Paid').first()).toBeVisible();

    await page.goto('/accounting/expenses?date_from=2026-10-31&date_to=2026-10-31');
    await expect(page.locator('table tbody tr').filter({ hasText: 'Enseignant' })).toContainText('7 350');
});

test('téléversement et récupération sécurisée d’un document élève', async ({ page }) => {
    await page.goto('/students?search=LPP-E2E-001');
    const studentIndexRow = page.locator('table tbody tr').filter({ hasText: SEEDED_MATRICULE });
    await expect(studentIndexRow).toContainText(SEEDED_STUDENT);
    await studentIndexRow.getByRole('link', { name: 'Voir' }).click();
    const documentForm = page.locator('form[enctype="multipart/form-data"]');
    await documentForm.locator('input[name="name"]').fill('Acte E2E navigateur');
    await documentForm.locator('select[name="document_type"]').selectOption('birth_certificate');
    await documentForm.locator('select[name="status"]').selectOption('received');
    await documentForm.locator('input[name="document_file"]').setInputFiles({
        name: 'acte-e2e.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'),
    });
    await documentForm.getByRole('button', { name: 'Ajouter au dossier' }).click();

    const documentRow = page.locator('table tbody tr').filter({ hasText: 'Acte E2E navigateur' });
    await expect(documentRow).toContainText('Reçu');
    const downloadPromise = page.waitForEvent('download');
    await documentRow.getByRole('link', { name: 'Télécharger' }).click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toMatch(/lpp-e2e-001-acte-e2e-navigateur\.pdf$/);
    expect(await download.createReadStream()).not.toBeNull();
});
