import {expect, test} from '@playwright/test';
import {storageStates} from './support/globalSetup.js';
import {dataverseCredentials} from './support/testData.js';

test.use({storageState: storageStates.manager});

const PLUGIN_ROW = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin';

async function openSettingsForm(page) {
	await page.goto('index.php/publicknowledge/management/settings/website');
	await page.locator('button#plugins-button').click();
	await page.locator(`tr#${PLUGIN_ROW} a.show_extras`).click();
	await page.locator(`a[id^=${PLUGIN_ROW}-settings-button]`).click();
	return page.locator('form#dataverseConfigurationForm');
}

test('checks required fields and URLs in the browser before saving the configuration', async ({page}) => {
	const credentials = dataverseCredentials();
	const form = await openSettingsForm(page);
	const dataverseUrl = form.locator('input[name=dataverseUrl]');
	const apiToken = form.locator('input[name=apiToken]');
	const termsOfUse = form.locator('input[name="termsOfUse[en]"]');
	const save = form.getByRole('button', {name: 'OK'});

	await dataverseUrl.fill('');
	await apiToken.fill('');
	await termsOfUse.fill('');
	await save.click();
	await expect(form.locator('label[for^=dataverseUrl].error')).toHaveText('This field is required.');
	await expect(form.locator('label[for^=apiToken].error')).toHaveText('This field is required.');
	await expect(form.locator('label[for^=termsOfUse].error')).toHaveText('This field is required.');

	await dataverseUrl.fill('dataverseUrl');
	await termsOfUse.fill('invalidTermsOfUse');
	await save.click();
	await expect(form.locator('label[for^=dataverseUrl].error')).toHaveText('Please enter a valid URL.');
	await expect(form.locator('label[for^=termsOfUse].error')).toHaveText('Please enter a valid URL.');

	await dataverseUrl.fill(credentials.url);
	await apiToken.fill(credentials.apiToken);
	await termsOfUse.fill(credentials.termsOfUse);
	await save.click();
	await expect(page.getByText('Your changes have been saved.')).toBeVisible();
});
