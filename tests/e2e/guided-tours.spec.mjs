import { expect, test } from '@playwright/test';

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Identifiant').fill('admin');
    await page.getByLabel('Mot de passe').fill('e2e-admin-secret');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

test('la visite du tableau de bord peut être ouverte, parcourue et fermée au clavier', async ({ page }, testInfo) => {
    await page.addInitScript(() => {
        window.localStorage.clear();
        window.sessionStorage.clear();
    });

    await login(page);

    const invitation = page.locator('.guided-tour-invitation');
    await expect(invitation).toBeVisible();
    await invitation.getByRole('button', { name: 'Commencer la visite' }).click();

    const card = page.locator('.guided-tour-card');
    await expect(card).toBeVisible();
    await expect(card.getByText(/1 sur \d+/)).toBeVisible();
    await card.getByRole('button', { name: 'Suivant' }).click();
    await expect(card.getByText(/2 sur \d+/)).toBeVisible();

    const screenshotPath = testInfo.outputPath(`visite-guidee-${testInfo.project.name}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: false });
    await testInfo.attach(`visite-guidee-${testInfo.project.name}`, {
        path: screenshotPath,
        contentType: 'image/png',
    });

    await page.keyboard.press('Escape');
    await expect(card).toBeHidden();
    await expect(page.locator('html')).not.toHaveClass(/has-guided-tour/);
});

test('une visite terminée est indiquée dans la documentation et peut être réinitialisée', async ({ page }) => {
    await login(page);
    await page.evaluate(() => {
        const userId = window.LPP_GUIDED_TOURS.userId;
        window.localStorage.setItem(`lpp:guided-tour:${userId}:dashboard`, JSON.stringify({ status: 'completed' }));
    });
    await page.goto('/help');

    const dashboardCard = page.locator('[data-guided-tour-card="dashboard"]');
    await expect(dashboardCard.getByText('Terminée')).toBeVisible();
    await expect(dashboardCard.getByRole('link', { name: 'Revoir' })).toBeVisible();

    await page.getByRole('button', { name: 'Réinitialiser mes visites' }).click();
    await expect(dashboardCard.getByText('À découvrir')).toBeVisible();
});

test('la visite reste dans la largeur de l’écran', async ({ page }) => {
    await login(page);
    await page.goto('/dashboard?tour=dashboard');
    await expect(page.locator('.guided-tour-card')).toBeVisible();

    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );
    expect(overflow).toBe(false);
});
