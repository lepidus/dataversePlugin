import {expect, test} from '@playwright/test';
import {storageStates} from './support/globalSetup.js';
import {addResearchDataFile} from './support/researchData.js';
import {
	REQUIRED_METADATA_LABELS,
	configureCollectionWithRequiredMetadata,
	configurePlugin,
	createSubmission,
	deleteDeposit,
	eventLog,
} from './support/testData.js';

test.use({storageState: storageStates.author});

const REPOSITORY_URL = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM';

async function openWizard(page, scenario) {
	const submission = createSubmission(scenario);
	await page.goto(`index.php/publicknowledge/submission?id=${submission.id}`);
	return submission;
}

const STEP_IDS = ['details', 'files', 'contributors', 'editors', 'review'];

async function openStep(page, stepId) {
	const heading = page.getByRole('heading', {level: 1});
	const stepNames = (await page.locator('.pkpSteps__step__label').allInnerTexts())
		.map((label) => label.replace(/^[\d\s]+/, '').trim());
	const headingText = await heading.textContent();
	const current = stepNames.findIndex((name) => headingText.includes(`Make a Submission: ${name}`));
	const target = STEP_IDS.indexOf(stepId);

	if (target < current) {
		await page.locator('.pkpSteps__step__label', {hasText: stepNames[target]}).click();
	}
	for (let step = current + 1; step <= target; step++) {
		await page.locator('.submissionWizard__footer').getByRole('button', {name: 'Continue'}).click();
		await expect(heading).toContainText(`Make a Submission: ${stepNames[step]}`);
	}
	await expect(heading).toContainText(`Make a Submission: ${stepNames[target]}`);
}

async function openReview(page) {
	await openStep(page, 'review');
	await expect(page.locator('.submissionWizard__loadingReview')).toHaveCount(0);
}

function dataStatementSection(page) {
	return page.locator('.panelSection', {has: page.getByRole('heading', {name: 'Data statement', level: 2})});
}

async function selectDataStatementType(page, type) {
	await page.locator(`input[name="dataStatementTypes"][value="${type}"]`).check();
}

test('data statement fields follow the selected statement types', async ({page}) => {
	await openWizard(page, 'details');
	const urls = page.locator('#dataStatement-dataStatementUrls-control');
	const reason = page.locator('#dataStatement-dataStatementReason-control-en');
	const review = page.locator('[data-cy="dataverse-review-data-statement"]');

	await expect(dataStatementSection(page)).toBeVisible();
	await expect(urls).toHaveCount(0);
	await expect(reason).toHaveCount(0);
	await expect(dataStatementSection(page).locator('.pkpFormLocales')).toBeHidden();

	await selectDataStatementType(page, 2);
	await expect(page.getByText('Insert the URLs to the data')).toBeVisible();
	await expect(urls).toBeVisible();
	await openReview(page);
	await expect(review).toContainText('It is required to inform the URLs to the data in repositories');

	await openStep(page, 'details');
	await urls.fill('Example text');
	await urls.press('Enter');
	await expect(page.getByText('This is not a valid URL.', {exact: true})).toBeVisible();
	await urls.fill(REPOSITORY_URL);
	await urls.press('Enter');
	await expect(page.getByText('This is not a valid URL.', {exact: true})).toHaveCount(0);

	await selectDataStatementType(page, 5);
	await expect(page.getByText('Provide the justification for the unavailability of the data')).toBeVisible();
	await expect(reason).toBeVisible();
	await expect(dataStatementSection(page).locator('.pkpFormLocales')).toBeVisible();
	await openReview(page);
	await expect(review).toContainText('It is required to inform the justification for the unavailability of the data');

	await openStep(page, 'details');
	await reason.fill('Has sensitive data');
	await openReview(page);
	await expect(review.locator('li', {hasText: 'The research data is available in one or more data repository(ies)'})).toBeVisible();
	await expect(review.locator('li', {hasText: 'The research data cannot be made publicly available'})).toBeVisible();
	await expect(review.getByRole('link', {name: REPOSITORY_URL})).toBeVisible();
	await expect(review).toContainText('Has sensitive data');
	await expect(review.locator('.pkpNotification--warning')).toHaveCount(0);
});

test('research data sections appear only when depositing in Dataverse', async ({page}) => {
	await openWizard(page, 'details');
	const researchData = page.locator('.panelSection', {has: page.locator('[data-cy="dataverse-research-data"]')});
	const researchDataMetadata = page.locator('.panelSection', {has: page.getByRole('heading', {name: 'Research data metadata', level: 2})});

	await openStep(page, 'files');
	await expect(researchData).toBeHidden();
	await openStep(page, 'editors');
	await expect(researchDataMetadata).toBeHidden();
	await openReview(page);
	await expect(page.locator('[data-cy="dataverse-review-research-data"]')).toHaveCount(0);
	await expect(page.locator('[data-cy="dataverse-review-dataset-metadata"]')).toHaveCount(0);

	await openStep(page, 'details');
	await expect(page.getByText('The research data will be sent in subsequent stages of this submission, so that it can be deposited in the repository')).toBeVisible();
	await expect(dataStatementSection(page).getByRole('link', {name: process.env.DATAVERSE_COLLECTION_NAME})).toBeVisible();
	await selectDataStatementType(page, 3);

	await openStep(page, 'files');
	await expect(researchData).toBeVisible();
	const instructions = page.locator('[data-cy="dataverse-additional-instructions"]');
	await expect(instructions).toContainText('1. Submit under "Research Data" any files that have been collected');
	await expect(instructions).toContainText('2. It is mandatory to include a file named "Readme"/"Leiame"/"Leame"');
	await expect(instructions).toContainText('For additional guidance on creating the file, consult the suggested references below');
	await expect(instructions).toContainText('3. The files deposited in "Research Data" will form a dataset');
	await openStep(page, 'editors');
	await expect(researchDataMetadata).toBeVisible();
	await openReview(page);
	await expect(page.locator('[data-cy="dataverse-review-research-data"]')).toBeVisible();
	await expect(page.locator('[data-cy="dataverse-review-dataset-metadata"]')).toBeVisible();
});

test('research data files are added and deleted in the upload step', async ({page}) => {
	const submission = await openWizard(page, 'dataverse');
	const review = page.locator('[data-cy="dataverse-review-research-data"]');

	await openStep(page, 'files');
	await expect(page.getByText('Use this field only for submitting research data')).toBeVisible();
	await addResearchDataFile(page, 'Data_detailing.pdf', 'application/pdf', '%PDF-1.4 data detailing');
	await addResearchDataFile(page, 'Planilha_de_dados_ÇÕÔÁÀÃ.json', 'application/json', '{"measurements": [1, 2, 3]}');

	await page.locator('.listPanel__item', {hasText: 'Data_detailing.pdf'}).locator('[data-cy="dataverse-delete-file"]').click();
	const dialog = page.getByRole('dialog').filter({hasText: 'Are you sure you want to permanently delete the research data file Data_detailing.pdf?'});
	await dialog.getByRole('button', {name: 'Delete File'}).click();
	await expect(page.locator('[data-cy="dataverse-file-name"]', {hasText: 'Data_detailing.pdf'})).toHaveCount(0);

	await openReview(page);
	await expect(review.getByRole('link', {name: 'Planilha_de_dados_ÇÕÔÁÀÃ.json'})).toBeVisible();
	await expect(review.getByRole('link', {name: 'Data_detailing.pdf'})).toHaveCount(0);
	await expect(review).toContainText('It is mandatory to send a README file, in PDF, MD or TXT format, to accompany the research data files');
	expect(eventLog(submission.id)).toEqual(expect.arrayContaining([
		{message: 'File "Data_detailing.pdf" added as research data.', username: 'eostrom'},
		{message: 'File "Planilha_de_dados_ÇÕÔÁÀÃ.json" added as research data.', username: 'eostrom'},
		{message: 'File "Data_detailing.pdf" deleted from research data.', username: 'eostrom'},
	]));
});

test.describe('submission deposit', () => {
	let submission;

	test.afterEach(async ({}, testInfo) => {
		if (submission && testInfo.status !== testInfo.expectedStatus) {
			deleteDeposit(submission.id);
		}
	});

	test('dataset metadata is filled in, reviewed and deposited on submission', async ({page}) => {
		submission = await openWizard(page, 'ready-to-submit');
		const review = page.locator('[data-cy="dataverse-review-dataset-metadata"]');

		await openStep(page, 'editors');
		await expect(page.getByText('Please provide the following details about the research data you are submitting')).toBeVisible();
		await expect(page.locator('select[name="datasetLanguage"]')).toHaveValue('English');
		await expect(page.locator('select[name="datasetLicense"]')).toHaveValue('CC0 1.0');
		await expect(page.locator('select[name="datasetRelationType"]')).toHaveValue('IsCitedBy');

		await openReview(page);
		for (const label of ['Research Data Language', 'Research Data Subject', 'Research Data License', 'Research Data Relation Type']) {
			await expect(review).toContainText(label);
		}
		await expect(review).toContainText('The subject of the research data is required');

		await openStep(page, 'editors');
		await page.locator('select[name="datasetLanguage"]').selectOption('French');
		await page.locator('select[name="datasetSubject"]').selectOption('Earth and Environmental Sciences');
		await page.locator('select[name="datasetLicense"]').selectOption('CC BY 4.0');
		await page.locator('select[name="datasetRelationType"]').selectOption({label: 'Is Supplemented By'});

		await openReview(page);
		await expect(review).not.toContainText('The subject of the research data is required');
		await page.reload();
		await openReview(page);
		for (const value of ['French', 'Earth and Environmental Sciences', 'CC BY 4.0', 'Is Supplemented By']) {
			await expect(review).toContainText(value);
		}

		await page.locator('.submissionWizard__footer').getByRole('button', {name: 'Submit'}).click();
		await page.getByRole('dialog').getByRole('button', {name: 'Submit'}).click();
		await expect(page.getByRole('heading', {name: 'Submission complete'})).toBeVisible({timeout: 60000});
		expect(deleteDeposit(submission.id).deleted).toBe(true);
	});
});

test.describe('with a collection that requires additional metadata', () => {
	test.beforeAll(() => configureCollectionWithRequiredMetadata());
	test.afterAll(() => configurePlugin());

	test('the collection required metadata is filled in and validated in the review', async ({page}) => {
		await openWizard(page, 'ready-to-submit');
		const review = page.locator('[data-cy="dataverse-review-dataset-metadata"]');
		const reviewItem = (field) => review.locator('.submissionWizard__reviewPanel__item', {
			has: page.getByRole('heading', {name: REQUIRED_METADATA_LABELS[field], exact: true}),
		});
		const field = (name) => page.locator(`#datasetMetadata-${name}-control`);

		await openStep(page, 'editors');
		for (const label of Object.values(REQUIRED_METADATA_LABELS)) {
			await expect(page.locator('.pkpFormFieldLabel', {hasText: label})).toBeVisible();
		}

		await openReview(page);
		for (const name of Object.keys(REQUIRED_METADATA_LABELS)) {
			await expect(reviewItem(name)).toContainText('This field is required.');
		}

		await openStep(page, 'editors');
		await field('datasetAlternativeURL').fill('invalid-url');
		await field('datasetDsDescriptionDate').fill('june 32, 2023');
		await openReview(page);
		await expect(reviewItem('datasetAlternativeURL')).toContainText('This is not a valid URL.');
		await expect(reviewItem('datasetDsDescriptionDate')).toContainText('This is not a valid date.');

		await openStep(page, 'editors');
		await field('datasetAlternativeURL').fill('https://example.com');
		await field('datasetDsDescriptionDate').fill('2023-06-01');
		await field('datasetPSRI1').selectOption('Yes');
		await field('datasetPSRI2').selectOption('No');
		await openReview(page);
		await expect(review).not.toContainText(/This field is required\.|This is not a valid/);
	});
});
