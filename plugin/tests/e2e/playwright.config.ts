import { defineConfig } from '@playwright/test';

// Runs against a running Sylius store with the plugin installed (default: the Laragon virtual host).
export default defineConfig({
    testDir: './specs',
    fullyParallel: false,
    workers: 1,
    timeout: 180_000,
    expect: { timeout: 15_000 },
    reporter: [['list']],
    use: {
        baseURL: process.env.BASE_URL ?? 'https://sylius-hackataon-lyon.gs',
        ignoreHTTPSErrors: true,
        navigationTimeout: 60_000,
        trace: 'retain-on-failure',
    },
});
