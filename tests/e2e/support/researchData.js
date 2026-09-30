import {expect} from '@playwright/test';
import {dataverseCredentials} from './testData.js';

export async function addResearchDataFile(page, name, mimeType, contents) {
	const credentials = dataverseCredentials();
	await page.locator('[data-cy="dataverse-add-file"]').click();
	const form = page.locator('[data-cy="dataverse-add-file-form"]');
	await expect(form.locator('legend', {hasText: 'Dataverse terms of use'})).toBeVisible();
	await expect(form.getByRole('link', {name: 'Terms of Use'})).toHaveAttribute('href', credentials.termsOfUse);
	const uploaded = page.waitForResponse((response) => response.url().includes('/temporaryFiles') && response.ok());
	await form.locator('input[type="file"]').setInputFiles({name, mimeType, buffer: Buffer.from(contents)});
	await uploaded;
	await expect(form.getByText(name).first()).toBeVisible();
	await form.locator('input[name="termsOfUse"]').check();
	await form.getByRole('button', {name: 'Save'}).click();
	await expect(form).toBeHidden();
	await expect(page.locator('[data-cy="dataverse-file-name"]', {hasText: name})).toBeVisible();
}

export async function openPanel(page, submission, panelName) {
	await page.goto(`index.php/publicknowledge/dashboard/${page.dashboard}?workflowSubmissionId=${submission.id}`);
	await page.getByRole('link', {name: panelName, exact: true}).click();
}

export async function openResearchData(page, submission) {
	await openPanel(page, submission, 'Research data');
	const panel = page.locator('.dataverseResearchData');
	await expect(panel.locator('[data-cy="dataverse-citation"], .dataverseResearchData__empty')).toBeVisible({timeout: 30000});
	return panel;
}
