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

    const studentCreatedDialog = page.locator('#student-created-dialog');
    await expect(studentCreatedDialog).toBeVisible();
    await expect(studentCreatedDialog.getByRole('button', { name: 'Ajouter des documents' })).toBeVisible();
    await studentCreatedDialog.getByRole('link', { name: 'Inscrire maintenant' }).click();

    await expect(page.locator('#student_id option:checked')).toContainText('Nadine Navigateur');
    await selectOptionContaining(page.locator('#school_class_id'), SEEDED_CLASS);
    await page.locator('#enrollment_date').fill('2026-10-02');
    await page.getByRole('button', { name: "Enregistrer l'inscription" }).click();

    await expect(page).toHaveURL(/\/enrollments\/\d+$/);
    const enrollmentCreatedDialog = page.locator('#enrollment-created-dialog');
    await expect(enrollmentCreatedDialog).toBeVisible();
    await expect(enrollmentCreatedDialog.getByRole('button', { name: 'Premier paiement' })).toBeVisible();
    await expect(enrollmentCreatedDialog.getByRole('link', { name: /Fiche d.inscription/ })).toBeVisible();
    await expect(enrollmentCreatedDialog.getByRole('link', { name: 'Carte scolaire' })).toBeVisible();
    await expect(enrollmentCreatedDialog.getByRole('link', { name: 'Voir la classe' })).toBeVisible();
});

test('paiement, reçu PDF puis annulation motivée', async ({ page }) => {
    await page.goto('/payments');
    await page.getByRole('link', { name: 'Nouveau paiement' }).click();

    const paymentDialog = page.locator('#payment-create-dialog');
    await expect(paymentDialog).toBeVisible();
    await selectOptionContaining(paymentDialog.locator('[data-payment-student]'), SEEDED_MATRICULE);
    await selectOptionContaining(paymentDialog.locator('[data-payment-schedule]').first(), 'Inscription E2E');
    await expect(paymentDialog.locator('[data-payment-amount]').first()).toHaveValue('25000');
    await paymentDialog.locator('input[name="paid_at"]').fill('2026-10-10T10:30');
    await paymentDialog.getByRole('button', { name: 'Enregistrer le paiement' }).click({ clickCount: 2, delay: 10 });

    await expect(page).toHaveURL(/\/payments\/\d+$/);
    const paymentUrl = page.url();
    const successDialog = page.locator('#payment-success-dialog');
    await expect(successDialog).toBeVisible();
    await expect(successDialog.getByText('25 000 FCFA')).toBeVisible();

    const receiptHref = await successDialog.getByRole('link', { name: 'Télécharger le reçu' }).getAttribute('href');
    const receipt = await page.request.get(receiptHref);
    expect(receipt.ok()).toBe(true);
    expect(receipt.headers()['content-type']).toContain('application/pdf');
    expect((await receipt.body()).byteLength).toBeGreaterThan(1000);

    await page.goto(`/payments?search=${SEEDED_MATRICULE}`);
    await expect(page.locator('table tbody tr').filter({ hasText: SEEDED_STUDENT })).toHaveCount(1);
    await page.goto(paymentUrl);

    await page.locator('button[data-dialog-open="cancel-payment-dialog"]').click();
    const cancellationDialog = page.locator('#cancel-payment-dialog');
    await expect(cancellationDialog).toBeVisible();
    await cancellationDialog.getByLabel('Motif d’annulation').fill('Annulation automatique du test navigateur');
    await cancellationDialog.getByRole('button', { name: 'Confirmer l’annulation' }).click();

    await expect(page.getByText('Annulé').first()).toBeVisible();
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

    await page.goto('/attendance');
    const incidentRow = page.locator('table tbody tr').filter({ hasText: SEEDED_MATRICULE });
    await incidentRow.getByRole('button', { name: 'Traiter' }).click();
    const attendanceDialog = page.locator('#attendance-record-dialog');
    await expect(attendanceDialog).toBeVisible();
    await attendanceDialog.locator('textarea[name="reason"]').fill('Justification test navigateur');
    await attendanceDialog.getByRole('button', { name: 'Enregistrer la justification' }).click();
    await expect(page.getByText('Absence ou retard justifié avec succès.')).toBeVisible();

    await page.goto('/communications?tab=history&search=parent.e2e@gmail.com');
    const messageRow = page.locator('table tbody tr')
        .filter({ hasText: 'parent.e2e@gmail.com' })
        .filter({ hasText: 'Absence / retard' });
    await expect(messageRow).toContainText('Absence / retard');
    await expect(messageRow).toContainText('Envoyé');
});

test('heures, ordre d’honoraires, paiement et dépense comptable', async ({ page }) => {
    await page.goto('/teacher-work-sessions');
    await page.getByRole('button', { name: 'Ajouter des heures' }).click();
    const workDialog = page.locator('#teacher-work-session-form-dialog');
    await expect(workDialog).toBeVisible();
    const workForm = workDialog.locator('form');
    await workForm.locator('select[name="teacher_id"]').selectOption({ label: 'Enseignant' });
    await workForm.locator('input[name="session_date"]').fill('2026-10-17');
    await workForm.locator('select[name="school_class_id"]').selectOption({ label: SEEDED_CLASS });
    await workForm.locator('select[name="subject_id"]').selectOption({ label: 'Français' });
    await workForm.locator('input[name="starts_at"]').fill('08:00');
    await workForm.locator('input[name="ends_at"]').fill('11:00');
    await workForm.locator('input[name="hours_worked"]').fill('3');
    await workForm.locator('select[name="status"]').selectOption('draft');
    await workDialog.getByRole('button', { name: 'Enregistrer les heures' }).click();
    await expect(page.getByText('Heures du professeur enregistrées.')).toBeVisible();

    await page.goto('/teacher-work-sessions?month=2026-10');
    const workRow = page.locator('table tbody tr').filter({ hasText: '17/10/2026' }).filter({ hasText: 'Enseignant' });
    await workRow.getByRole('button', { name: 'Gérer' }).click();
    const actionDialog = page.locator('#teacher-work-session-action-dialog');
    await expect(actionDialog).toBeVisible();
    await actionDialog.getByRole('button', { name: 'Confirmer la validation' }).click();
    await expect(page.getByText('Émargement validé.')).toBeVisible();

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
    await page.getByRole('button', { name: 'Payer les honoraires' }).click();
    const feeDialog = page.locator('#teacher-fee-payment-dialog');
    await expect(feeDialog).toBeVisible();
    await expect(feeDialog.getByText('7 350 FCFA')).toBeVisible();
    await feeDialog.locator('input[name="paid_at"]').fill('2026-10-31');
    await feeDialog.locator('select[name="payment_method"]').selectOption({ label: 'Virement' });
    await feeDialog.locator('input[name="payment_reference"]').fill('E2E-VIR-001');
    await feeDialog.getByRole('button', { name: 'Confirmer le paiement' }).click();

    const paidDialog = page.locator('#teacher-fee-paid-dialog');
    await expect(paidDialog).toBeVisible();
    await expect(paidDialog.getByRole('link', { name: 'PDF' })).toBeVisible();
    await expect(paidDialog.getByRole('link', { name: 'Dossier professeur' })).toBeVisible();
    await paidDialog.getByRole('link', { name: 'Dépense comptable' }).click();
    await expect(page.getByText('7 350 FCFA').first()).toBeVisible();
});

test('téléversement et récupération sécurisée d’un document élève', async ({ page }) => {
    await page.goto('/students?search=LPP-E2E-001');
    const studentIndexRow = page.locator('table tbody tr').filter({ hasText: SEEDED_MATRICULE });
    await expect(studentIndexRow).toContainText(SEEDED_STUDENT);
    await studentIndexRow.getByRole('link', { name: 'Voir' }).click();
    await page.getByRole('link', { name: 'Ajouter une pièce' }).click();
    const documentDialog = page.locator('#student-document-dialog');
    await expect(documentDialog).toBeVisible();
    const documentForm = documentDialog.locator('form[enctype="multipart/form-data"]');
    await documentForm.locator('input[name="name"]').fill('Acte E2E navigateur');
    await documentForm.locator('select[name="document_type"]').selectOption('birth_certificate');
    await documentForm.locator('select[name="status"]').selectOption('received');
    await documentForm.locator('input[name="document_file"]').setInputFiles({
        name: 'acte-e2e.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'),
    });
    await documentDialog.getByRole('button', { name: 'Ajouter au dossier' }).click();

    const documentRow = page.locator('table tbody tr').filter({ hasText: 'Acte E2E navigateur' });
    await expect(documentRow).toContainText('Reçu');
    const downloadPromise = page.waitForEvent('download');
    await documentRow.getByRole('link', { name: 'Télécharger' }).click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toMatch(/lpp-e2e-001-acte-e2e-navigateur\.pdf$/);
    expect(await download.createReadStream()).not.toBeNull();
});
