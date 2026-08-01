import { defineConfig, devices } from '@playwright/test';
import { appEnvironment, baseURL, serverPort } from './tests/e2e/environment.mjs';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.mjs',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI
        ? [['line'], ['html', { open: 'never' }]]
        : [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
              command: `php artisan serve --host=127.0.0.1 --port=${serverPort}`,
              env: appEnvironment,
              reuseExistingServer: false,
              timeout: 120_000,
              url: baseURL,
          },
    projects: [
        {
            name: 'chromium-desktop',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'chromium-mobile',
            use: { ...devices['Pixel 7'] },
        },
        {
            name: 'chromium-tablet',
            use: { ...devices['iPad (gen 7)'], browserName: 'chromium' },
        },
    ],
});
