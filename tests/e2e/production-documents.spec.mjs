import { expect, test } from '@playwright/test';

const LIVE_TEST_ENABLED = process.env.E2E_LIVE === 'true';
const USERNAME = process.env.E2E_LIVE_USERNAME;
const PASSWORD = process.env.E2E_LIVE_PASSWORD;

test.describe('documents de production en lecture seule', () => {
    test.skip(!LIVE_TEST_ENABLED, 'Définir E2E_LIVE=true pour contrôler la production.');
    test.skip(!USERNAME || !PASSWORD, 'Les identifiants E2E_LIVE sont requis.');

    test('tous les PDF disponibles répondent avec un contenu valide', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium-desktop', 'Contrôle exécuté une seule fois.');
        test.setTimeout(360_000);

        await page.goto('/login');
        await page.getByLabel('Identifiant').fill(USERNAME);
        await page.getByLabel('Mot de passe').fill(PASSWORD);
        await page.getByRole('button', { name: 'Se connecter' }).click();
        await expect(page).toHaveURL(/\/dashboard$/);

        const documentUrls = new Set();
        const isPdfUrl = (href) => /\/pdf(?:$|[?#])|\/receipt(?:$|[?#])/.test(href);

        const collectLinks = async (path) => {
            const response = await page.goto(path, { waitUntil: 'domcontentloaded' });

            if (!response || response.status() >= 400) {
                return [];
            }

            const hrefs = await page.locator('a[href]').evaluateAll((anchors) =>
                anchors.map((anchor) => anchor.href),
            );

            hrefs.filter(isPdfUrl).forEach((href) => documentUrls.add(href));

            return hrefs;
        };

        const sections = [
            '/attendance',
            '/report-cards',
            '/timetables',
            '/class-council',
            '/accounting/balance-sheet',
            '/accounting/cash-journal',
            '/accounting/expenses',
            '/teacher-attendance-sheets',
        ];

        for (const path of sections) {
            await collectLinks(path);
        }

        const mockExamLinks = await collectLinks('/mock-exams');
        const mockExamDocumentsUrl = mockExamLinks.find((href) => {
            const url = new URL(href);

            return url.pathname === '/mock-exams' && url.searchParams.get('section') === 'documents';
        });

        if (mockExamDocumentsUrl) {
            await collectLinks(mockExamDocumentsUrl);
        }

        const detailPages = [
            ['/payments', /\/payments\/\d+$/],
            ['/students', /\/students\/\d+$/],
            ['/teachers', /\/teachers\/\d+$/],
            ['/teacher-fees', /\/teacher-fees\/\d+$/],
            ['/certificates', /\/certificates\/\d+$/],
        ];

        for (const [indexPath, detailPattern] of detailPages) {
            const hrefs = await collectLinks(indexPath);
            const detailUrl = hrefs.find((href) => detailPattern.test(new URL(href).pathname));

            if (detailUrl) {
                await collectLinks(detailUrl);
            }
        }

        expect(documentUrls.size).toBeGreaterThan(0);

        const failures = [];
        const categories = new Set();

        for (const url of [...documentUrls].sort()) {
            const response = await page.request.get(url, { timeout: 60_000 });
            const body = await response.body();
            const pathname = new URL(url).pathname;
            const contentType = response.headers()['content-type'] ?? '';
            const isPdf = contentType.includes('application/pdf')
                && body.subarray(0, 4).toString() === '%PDF';

            categories.add(pathname.split('/').filter(Boolean)[0]);

            if (response.status() !== 200 || !isPdf || body.length < 1000) {
                failures.push({
                    path: pathname,
                    status: response.status(),
                    contentType,
                    bytes: body.length,
                });
            }
        }

        expect(failures).toEqual([]);
        expect([...categories].sort()).toEqual(expect.arrayContaining([
            'accounting',
            'attendance',
            'certificates',
            'class-council',
            'mock-exams',
            'payments',
            'report-cards',
            'students',
            'teachers',
            'timetables',
        ]));

        console.log(
            `[production-documents] ${documentUrls.size} PDF valides dans ${categories.size} categories.`,
        );
    });
});
