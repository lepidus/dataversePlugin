import {mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {chromium} from '@playwright/test';
import {configurePlugin, dataverseCredentials} from './testData.js';

const storageState = (username) => join(process.env.DATAVERSE_E2E_AUTH_DIR ?? '', `${username}.json`);

export const storageStates = {
	get manager() {
		return storageState('dbarnes');
	},
	get author() {
		return storageState('eostrom');
	},
	get sectionEditor() {
		return storageState('dbuskins');
	},
};

async function fetchCollectionName({url, apiToken}) {
	const {origin, pathname} = new URL(url);
	const alias = pathname.split('/').pop();
	const response = await fetch(`${origin}/api/dataverses/${alias}`, {
		headers: {'X-Dataverse-key': apiToken},
		redirect: 'error',
	});
	if (!response.ok) {
		throw new Error(`Dataverse collection "${alias}" could not be read: HTTP ${response.status}`);
	}
	const {data} = await response.json();
	return data.name;
}

export async function logIn(page, username) {
	await page.goto('index.php/publicknowledge/en/login');
	await page.locator('input#username').fill(username);
	await page.locator('input#password').fill(username + username);
	await page.locator('form#login button').click();
	await page.waitForURL(/dashboard|submissions/);
}

async function saveLogin(browser, baseURL, username, storageState) {
	const page = await browser.newPage({baseURL});
	await logIn(page, username);
	await page.context().storageState({path: storageState});
	await page.close();
}

export default async function globalSetup(config) {
	const credentials = dataverseCredentials();
	process.env.DATAVERSE_COLLECTION_NAME = await fetchCollectionName(credentials);
	process.env.PKP_APPLICATION = configurePlugin().application;

	process.env.DATAVERSE_E2E_AUTH_DIR = mkdtempSync(join(tmpdir(), 'dataverse-e2e-'));
	const {baseURL} = config.projects[0].use;
	const browser = await chromium.launch();
	await saveLogin(browser, baseURL, 'dbarnes', storageStates.manager);
	await saveLogin(browser, baseURL, 'eostrom', storageStates.author);
	await saveLogin(browser, baseURL, 'dbuskins', storageStates.sectionEditor);
	await browser.close();
}
