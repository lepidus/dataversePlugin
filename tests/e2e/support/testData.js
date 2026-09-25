import {execFileSync} from 'node:child_process';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const pluginDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const appRoot = process.env.APP_ROOT ?? path.resolve(pluginDir, '../../..');

function runTestData(...args) {
	const stdout = execFileSync(
		'php',
		['plugins/generic/dataverse/tests/e2e/support/DataverseTestData.php', ...args],
		{cwd: appRoot, encoding: 'utf8'},
	);
	return JSON.parse(stdout.trim().split('\n').pop());
}

export const configurePlugin = () => runTestData('configure');
export const createSubmission = (scenario = 'details') => runTestData('create-submission', scenario);
export const deleteDeposit = (submissionId) => runTestData('delete-dataset', String(submissionId));

export function dataverseCredentials() {
	const credentials = {
		url: process.env.DATAVERSE_URL?.replace(/\/+$/, ''),
		apiToken: process.env.DATAVERSE_API_TOKEN,
		termsOfUse: process.env.DATAVERSE_TERMS_OF_USE,
	};
	if (Object.values(credentials).some((value) => !value)) {
		throw new Error('Set DATAVERSE_URL, DATAVERSE_API_TOKEN and DATAVERSE_TERMS_OF_USE');
	}
	return credentials;
}
