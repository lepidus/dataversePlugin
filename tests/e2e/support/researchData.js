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
