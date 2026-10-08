import {expect, test} from '@playwright/test';
import {storageStates} from './support/globalSetup.js';
import {addResearchDataFile, openPanel, openResearchData} from './support/researchData.js';
import {
	REQUIRED_METADATA_LABELS,
	configureCollectionWithRequiredMetadata,
	configurePlugin,
	createSubmission,
	datasetMetadata,
	deleteDataset,
	deleteDeposit,
} from './support/testData.js';

const REPOSITORY_URL = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM';
const DASHBOARDS = {author: 'mySubmissions', manager: 'editorial'};

const createdSubmissions = [];

function createWorkflowSubmission(scenario, options = {}) {
	const submission = createSubmission(scenario, options);
	createdSubmissions.push(submission);
	return submission;
}

const createDepositedSubmission = (options) => createWorkflowSubmission('deposited', options);

test.afterEach(() => {
	while (createdSubmissions.length) {
		const {id, persistentId} = createdSubmissions.pop();
		deleteDeposit(id);
		if (persistentId) {
			deleteDataset(persistentId);
		}
	}
});

async function openDataStatement(page, submission) {
	await openPanel(page, submission, 'Data statement');
	const form = page.locator('[data-cy="dataverse-data-statement-form"]');
	await expect(form).toBeVisible();
	return form;
}

function statementType(form, type) {
	return form.locator(`input[name="dataStatementTypes"][value="${type}"]`);
}

async function openPublishDialog(page) {
	await page.getByRole('button', {name: /^(Schedule For Publication|Publish|Post)$/}).click();
	const dialog = page.getByRole('dialog').filter({hasText: /Are you sure you want to (publish|post) this\?/});
	await expect(dialog).toBeVisible();
	return dialog;
}

async function confirmDialog(page, message, action) {
	const dialog = page.getByRole('dialog').filter({hasText: message});
	await expect(dialog).toBeVisible();
	await dialog.getByRole('button', {name: action, exact: true}).click();
}

async function saveForm(page, form) {
	const saved = page.waitForResponse((response) => response.request().method() !== 'GET' && response.ok());
	await form.getByRole('button', {name: 'Save'}).click();
	await saved;
}

test.describe('as the author', () => {
	test.use({storageState: storageStates.author});
	test.beforeEach(({page}) => {
		page.dashboard = DASHBOARDS.author;
	});

	test('data statement panel shows and saves the statement of the publication', async ({page}) => {
		const submission = createDepositedSubmission({
			dataStatement: {
				dataStatementTypes: [2, 5],
				dataStatementUrls: [REPOSITORY_URL],
				dataStatementReason: {en: 'Has sensitive data'},
			},
		});
		let form = await openDataStatement(page, submission);
		const urls = form.getByRole('button', {name: 'Remove ' + REPOSITORY_URL});
		const reason = form.locator('#dataStatement-dataStatementReason-control-en');
		const researchDataSubmitted = form.locator('input[name="researchDataSubmitted"]');

		await expect(statementType(form, 2)).toBeChecked();
		await expect(statementType(form, 5)).toBeChecked();
		await expect(urls).toBeVisible();
		await expect(reason).toHaveValue('Has sensitive data');
		await expect(researchDataSubmitted).toBeChecked();
		await expect(researchDataSubmitted).toBeDisabled();

		await statementType(form, 2).uncheck();
		await expect(urls).toBeHidden();
		await statementType(form, 5).uncheck();
		await expect(reason).toBeHidden();
		await statementType(form, 2).check();
		await statementType(form, 5).check();
		await statementType(form, 1).check();
		await saveForm(page, form);

		await page.reload();
		form = await openDataStatement(page, submission);
		for (const type of [1, 2, 5]) {
			await expect(statementType(form, type)).toBeChecked();
		}
		await expect(urls).toBeVisible();
		await expect(reason).toHaveValue('Has sensitive data');
	});

	test('research data metadata is edited in the panel', async ({page}) => {
		const submission = createDepositedSubmission();
		let panel = await openResearchData(page, submission);
		const form = panel.locator('[data-cy="dataverse-metadata-form"]');
		const title = form.locator('input[name="datasetTitle"]');
		const description = form.frameLocator('iframe').locator('body');
		const keywords = form.getByRole('combobox', {name: 'Keyword'});

		await expect(panel.locator('[data-cy="dataverse-disassociate-dataset"]')).toHaveCount(0);
		await expect(panel.locator('[data-cy="dataverse-publish-dataset"]')).toHaveCount(0);
		await expect(panel.getByText('Draft', {exact: true})).toBeVisible();
		await expect(panel.getByText('Unpublished', {exact: true})).toBeVisible();
		await expect(title).toHaveValue('Replication data for: ' + submission.title);
		await expect(description).toContainText('Mass public transportation can be used as a way to reduce greenhouse gases emissions.');
		await expect(form.getByRole('button', {name: 'Remove mass public transport'})).toBeVisible();
		await expect(form.getByRole('button', {name: 'Remove sustainable cities'})).toBeVisible();
		await expect(form.locator('select[name="datasetLanguage"]')).toHaveValue('French');
		await expect(form.locator('select[name="datasetSubject"]')).toHaveValue('Earth and Environmental Sciences');
		await expect(form.locator('select[name="datasetLicense"]')).toHaveValue('CC BY 4.0');
		await expect(form.locator('select[name="datasetRelationType"]')).toHaveValue('IsSupplementedBy');

		await title.fill('Test metadata editing');
		await description.fill('new description');
		await keywords.fill('climate change');
		await keywords.press('Enter');
		await form.locator('select[name="datasetLanguage"]').selectOption('English');
		await form.locator('select[name="datasetSubject"]').selectOption('Computer and Information Science');
		await form.locator('select[name="datasetLicense"]').selectOption('CC0 1.0');
		await form.locator('select[name="datasetRelationType"]').selectOption({label: 'Is Cited By'});
		await saveForm(page, form);

		await page.reload();
		panel = await openResearchData(page, submission);
		await expect(title).toHaveValue('Test metadata editing');
		await expect(description).toContainText('new description');
		await expect(form.getByRole('button', {name: 'Remove climate change'})).toBeVisible();
		await expect(form.locator('select[name="datasetLanguage"]')).toHaveValue('English');
		await expect(form.locator('select[name="datasetSubject"]')).toHaveValue('Computer and Information Science');
		await expect(form.locator('select[name="datasetLicense"]')).toHaveValue('CC0 1.0');
		await expect(form.locator('select[name="datasetRelationType"]')).toHaveValue('IsCitedBy');
	});

	test('research data files are added and deleted in the panel', async ({page}) => {
		const submission = createDepositedSubmission();
		const panel = await openResearchData(page, submission);
		const fileNames = panel.locator('[data-cy="dataverse-file-name"]');

		await panel.getByRole('tab', {name: 'Files'}).click();
		await expect(fileNames.filter({hasText: 'Planilha_de_dados_ÇÕÔÁÀÃ.json'})).toBeVisible();
		await expect(fileNames.filter({hasText: 'LEIAME.pdf'})).toBeVisible();

		await addResearchDataFile(page, 'example.json', 'application/json', '{"example": true}');
		await expect(fileNames).toHaveCount(3);

		await panel.locator('.listPanel__item', {hasText: 'example.json'}).locator('[data-cy="dataverse-delete-file"]').click();
		await confirmDialog(page, 'Are you sure you want to permanently delete the research data file example.json?', 'Delete File');
		await expect(fileNames.filter({hasText: 'example.json'})).toHaveCount(0);
		await expect(fileNames).toHaveCount(2);
	});

	test('research data is deleted and deposited again from the panel', async ({page}) => {
		const submission = createDepositedSubmission();
		let panel = await openResearchData(page, submission);

		await panel.locator('[data-cy="dataverse-delete-dataset"]').click();
		await confirmDialog(page, 'Are you sure you want to permanently delete the research data related to this submission?', 'Delete');
		await expect(panel.getByText('No research data transferred.')).toBeVisible({timeout: 30000});

		const form = await openDataStatement(page, submission);
		await expect(form.locator('input[name="researchDataSubmitted"]')).not.toBeChecked();

		panel = await openResearchData(page, submission);
		await panel.locator('[data-cy="dataverse-upload-research-data"]').click();
		const depositForm = page.locator('[data-cy="dataverse-deposit-form"]');
		await addResearchDataFile(page, 'example.json', 'application/json', '{"example": true}');
		await depositForm.locator('select[name="datasetLanguage"]').selectOption('English');
		await depositForm.locator('select[name="datasetSubject"]').selectOption('Earth and Environmental Sciences');
		await depositForm.locator('select[name="datasetLicense"]').selectOption('CC BY 4.0');
		await depositForm.locator('select[name="datasetRelationType"]').selectOption({label: 'Is Cited By'});
		await depositForm.getByRole('button', {name: 'Save'}).click();
		await expect(page.getByText('It is mandatory to send a README file, in PDF, MD or TXT format, to accompany the research data files')).toBeVisible();

		await addResearchDataFile(page, 'README.pdf', 'application/pdf', '%PDF-1.4 readme of the research data');
		await depositForm.getByRole('button', {name: 'Save'}).click();
		await expect(panel.locator('[data-cy="dataverse-citation"]')).toContainText('Replication data for: ' + submission.title, {timeout: 60000});
	});

	test('research data cannot be changed without permission to edit the publication', async ({page}) => {
		const deposited = createDepositedSubmission({authorCanEdit: false});
		let panel = await openResearchData(page, deposited);

		await expect(panel.locator('[data-cy="dataverse-additional-instructions"]')).toContainText('1. Submit under "Research Data" any files that have been collected');
		await expect(panel.locator('[data-cy="dataverse-delete-dataset"]')).toBeDisabled();
		await expect(panel.locator('[data-cy="dataverse-metadata-form"]').getByRole('button', {name: 'Save'})).toBeDisabled();
		await panel.getByRole('tab', {name: 'Files'}).click();
		await expect(panel.locator('[data-cy="dataverse-add-file"]')).toBeDisabled();
		const deleteFileButtons = panel.locator('[data-cy="dataverse-delete-file"]');
		await expect(deleteFileButtons).toHaveCount(2);
		for (const deleteFileButton of await deleteFileButtons.all()) {
			await expect(deleteFileButton).toBeDisabled();
		}

		const withoutResearchData = createWorkflowSubmission('submitted', {authorCanEdit: false});
		panel = await openResearchData(page, withoutResearchData);
		await expect(panel.getByText('It is not possible to send research data.')).toBeVisible();
		await expect(panel.locator('[data-cy="dataverse-upload-research-data"]')).toHaveCount(0);
		await expect(panel.locator('[data-cy="dataverse-associate-dataset"]')).toHaveCount(0);
	});
});

test.describe('as the editor', () => {
	test.use({storageState: storageStates.manager});
	test.beforeEach(({page}) => {
		page.dashboard = DASHBOARDS.manager;
	});

	test('research data is deleted with an email notification to the dataset contact', async ({page}) => {
		const submission = createDepositedSubmission();
		const panel = await openResearchData(page, submission);

		await panel.locator('[data-cy="dataverse-delete-dataset"]').click();
		const form = page.locator('[data-cy="dataverse-delete-dataset-form"]');
		await expect(form.getByText('Send an email notification to the dataset contact')).toBeVisible();
		await expect(form.getByText('Do not send an email notification')).toBeVisible();
		await expect(form.frameLocator('iframe').locator('body')).toContainText(
			`The research data from the manuscript submission "${submission.title}" has been removed`,
		);
		await form.getByRole('button', {name: 'Delete and send email'}).click();

		await expect(panel.getByText('No research data transferred.')).toBeVisible({timeout: 30000});
		await expect(panel.locator('[data-cy="dataverse-additional-instructions"]')).toContainText('1. Submit under "Research Data" any files that have been collected');
	});

	test('research data is disassociated and associated again by its persistent id', async ({page}) => {
		const submission = createDepositedSubmission();
		const otherSubmission = createDepositedSubmission();
		const panel = await openResearchData(page, submission);
		const persistentUri = submission.persistentId.replace('doi:', 'https://doi.org/');

		await panel.locator('[data-cy="dataverse-disassociate-dataset"]').click();
		await confirmDialog(page, 'The dataset will remain in Dataverse but will no longer be accessible from this submission', 'Disassociate');
		await expect(panel.getByText('No research data transferred.')).toBeVisible({timeout: 30000});

		await panel.locator('[data-cy="dataverse-associate-dataset"]').click();
		const form = page.locator('[data-cy="dataverse-associate-form"]');
		const persistentId = form.locator('input[name="datasetPersistentId"]');
		await persistentId.fill(otherSubmission.persistentId);
		await form.getByRole('button', {name: 'Associate'}).click();
		await expect(page.getByText('The dataset entered is already associated with a submission in this context')).toBeVisible();

		await persistentId.fill('doi:10.12345/FK2/BLABLA.TESTE');
		await form.getByRole('button', {name: 'Associate'}).click();
		await expect(page.getByText('The dataset entered is not present at the Dataverse repository')).toBeVisible();

		await persistentId.fill(submission.persistentId);
		await form.getByRole('button', {name: 'Associate'}).click();
		await expect(panel.locator('[data-cy="dataverse-citation"]').getByRole('link', {name: persistentUri})).toBeVisible({timeout: 30000});
		await expect(panel.locator('[data-cy="dataverse-disassociate-dataset"]')).toBeVisible();
	});

	test('dataset is published from the panel after the submission, and only once', async ({page}) => {
		test.setTimeout(180000);
		const submission = createDepositedSubmission({
			stage: 'production',
			dataStatement: {
				dataStatementTypes: [2, 5],
				dataStatementUrls: [REPOSITORY_URL],
				dataStatementReason: {en: 'Has sensitive data'},
			},
		});
		const persistentUri = submission.persistentId.replace('doi:', 'https://doi.org/');

		await test.step('publishing the submission asks whether to publish the research data', async () => {
			await openPanel(page, submission, 'Title & Abstract');
			const dialog = await openPublishDialog(page);
			await expect(dialog).toContainText(`This submission contains deposited research data that is not yet public: ${persistentUri}`);
			await expect(dialog).toContainText('In case you choose to publish them, make sure they are suitable for publication in');
			await expect(dialog).toContainText('Would you like to publish the research data?');
			await expect(dialog.locator('input[name="shouldPublishResearchData"][value="1"]')).not.toBeChecked();
			await expect(dialog.locator('input[name="shouldPublishResearchData"][value="0"]')).not.toBeChecked();

			await dialog.getByText('No', {exact: true}).click();
			await dialog.getByRole('button', {name: /^(Publish|Post)$/}).click();
			await expect(page.getByRole('button', {name: /^(Unpublish|Unpost)$/})).toBeVisible();
		});

		const panel = await test.step('the dataset stays unpublished until the editor publishes it', async () => {
			const panel = await openResearchData(page, submission);
			await expect(panel.getByText('Draft', {exact: true})).toBeVisible();

			await panel.locator('[data-cy="dataverse-publish-dataset"]').click();
			await confirmDialog(page, 'Do you really want to publish the research data related to this submission? This action cannot be undone.', 'Yes');
			await expect(panel.locator('[data-cy="dataverse-citation"]')).toContainText(/, V1$/, {timeout: 60000});
			return panel;
		});

		await test.step('a published dataset can no longer be changed', async () => {
			await expect(panel.locator('[data-cy="dataverse-publish-dataset"]')).toHaveCount(0);
			await expect(panel.getByText('Draft', {exact: true})).toHaveCount(0);
			await expect(panel.getByText('Unpublished', {exact: true})).toHaveCount(0);
			await expect(panel.locator('[data-cy="dataverse-delete-dataset"]')).toBeDisabled();
			await expect(panel.locator('[data-cy="dataverse-metadata-form"]').getByRole('button', {name: 'Save'})).toBeDisabled();
			await panel.getByRole('tab', {name: 'Files'}).click();
			await expect(panel.locator('[data-cy="dataverse-add-file"]')).toBeDisabled();
		});

		await test.step('the public page shows the data statement and the dataset citation', async () => {
			const publicPage = process.env.PKP_APPLICATION === 'ojs2' ? 'article' : 'preprint';
			await page.goto(`index.php/publicknowledge/${publicPage}/view/${submission.id}`);

			const dataStatement = page.locator('.dataStatement');
			await expect(dataStatement.getByRole('heading', {name: 'Data statement'})).toBeVisible();
			await expect(dataStatement).toContainText('The research data is available in one or more data repository(ies)');
			await expect(dataStatement.getByRole('link', {name: REPOSITORY_URL})).toBeVisible();
			await expect(dataStatement).toContainText('The research data cannot be made publicly available');
			await expect(dataStatement).toContainText('Has sensitive data');

			const citation = page.locator('.data_citation');
			await expect(citation.getByRole('heading', {name: 'Research data'})).toBeVisible();
			await expect(citation).toContainText(`"Replication data for: ${submission.title}"`);
			await expect(citation.getByRole('link', {name: persistentUri})).toBeVisible();
			await expect(citation).toContainText(', V1');
		});

		await test.step('publishing a new version does not ask about the research data again', async () => {
			await openPanel(page, submission, 'Title & Abstract');
			await page.getByRole('button', {name: 'Create New Version'}).click();
			await confirmDialog(page, 'Are you sure you want to create a new version?', 'Yes');
			const dialog = await openPublishDialog(page);
			await expect(dialog).not.toContainText('Would you like to publish the research data?');
			await dialog.getByRole('button', {name: /^(Publish|Post)$/}).click();
			await expect(page.getByRole('button', {name: /^(Unpublish|Unpost)$/})).toBeVisible();

			const panel = await openResearchData(page, submission);
			await expect(panel.locator('[data-cy="dataverse-citation"]')).toContainText(/, V1$/);
		});
	});
});

test.describe('with a collection that requires additional metadata', () => {
	test.use({storageState: storageStates.author});
	test.beforeAll(() => configureCollectionWithRequiredMetadata());
	test.afterAll(() => configurePlugin());
	test.beforeEach(({page}) => {
		page.dashboard = DASHBOARDS.author;
	});

	test('research data is deposited from the panel with the collection required metadata', async ({page}) => {
		const submission = createWorkflowSubmission('submitted');
		const panel = await openResearchData(page, submission);
		await panel.locator('[data-cy="dataverse-upload-research-data"]').click();
		const depositForm = page.locator('[data-cy="dataverse-deposit-form"]');
		const field = (name) => depositForm.locator(`[name="${name}"]`);
		const fieldErrors = (label) => depositForm
			.locator('.pkpFormField', {has: page.locator('.pkpFormFieldLabel', {hasText: label})})
			.getByText('This field is required.');
		await addResearchDataFile(page, 'README.pdf', 'application/pdf', '%PDF-1.4 readme of the research data');
		await addResearchDataFile(page, 'example.json', 'application/json', '{"example": true}');
		await field('datasetLanguage').selectOption('English');
		await field('datasetSubject').selectOption('Earth and Environmental Sciences');
		await field('datasetLicense').selectOption('CC BY 4.0');
		await field('datasetRelationType').selectOption({label: 'Is Supplemented By'});
		await depositForm.getByRole('button', {name: 'Save'}).click();
		for (const label of Object.values(REQUIRED_METADATA_LABELS)) {
			await expect(fieldErrors(label)).toBeVisible();
		}

		await field('datasetAlternativeURL').fill('https://example.com');
		await field('datasetDsDescriptionDate').fill('2023-06-01');
		await field('datasetPSRI1').selectOption('Yes');
		await field('datasetPSRI2').selectOption('No');
		await depositForm.getByRole('button', {name: 'Save'}).click();
		await expect(panel.locator('[data-cy="dataverse-citation"]')).toContainText('Replication data for: ' + submission.title, {timeout: 60000});
		expect(datasetMetadata(submission.id)).toMatchObject({
			alternativeURL: 'https://example.com',
			dsDescriptionDate: '2023-06-01',
			PSRI1: 'Yes',
			PSRI2: 'No',
		});
	});
});
