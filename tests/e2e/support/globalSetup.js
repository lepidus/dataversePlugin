import {chromium} from '@playwright/test';
import {configurePlugin, dataverseCredentials} from './testData.js';

export const storageStates = {
	manager: 'tests/e2e/.auth/dbarnes.json',
	author: 'tests/e2e/.auth/eostrom.json',
};

async function fetchCollectionName({url, apiToken}) {
	const {origin, pathname} = new URL(url);
	const alias = pathname.split('/').pop();
	const response = await fetch(`${origin}/api/dataverses/${alias}`, {
		headers: {'X-Dataverse-key': apiToken},
	});
	if (!response.ok) {
		throw new Error(`Dataverse collection "${alias}" could not be read: HTTP ${response.status}`);
	}
	const {data} = await response.json();
	return data.name;
}

async function saveLogin(browser, baseURL, username, storageState) {
	const page = await browser.newPage({baseURL});
	await page.goto('index.php/publicknowledge/en/login');
	await page.locator('input#username').fill(username);
	await page.locator('input#password').fill(username + username);
	await page.locator('form#login button').click();
	await page.waitForURL(/dashboard|submissions/);
	await page.context().storageState({path: storageState});
	await page.close();
}

export default async function globalSetup(config) {
	const credentials = dataverseCredentials();
	process.env.DATAVERSE_COLLECTION_NAME = await fetchCollectionName(credentials);
	configurePlugin();

	const {baseURL} = config.projects[0].use;
	const browser = await chromium.launch();
	await saveLogin(browser, baseURL, 'dbarnes', storageStates.manager);
	await saveLogin(browser, baseURL, 'eostrom', storageStates.author);
	await browser.close();
}
