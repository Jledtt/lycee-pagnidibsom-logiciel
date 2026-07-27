import { expect, test } from '@playwright/test';

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Identifiant').fill('admin');
    await page.getByLabel('Mot de passe').fill('Pagnidibsom');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
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
