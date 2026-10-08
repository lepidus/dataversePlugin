import {defineConfig} from '@playwright/test';

export default defineConfig({
	testDir: './tests/e2e',
	workers: 1,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	timeout: 60000,
	expect: {timeout: 15000},
	reporter: process.env.CI
		? [['list'], ['junit', {outputFile: 'results/e2e-junit.xml'}]]
		: 'list',
	globalSetup: './tests/e2e/support/globalSetup.js',
	use: {
		baseURL: process.env.BASE_URL ?? 'http://localhost:8000',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [{name: 'chromium', use: {browserName: 'chromium'}}],
});
