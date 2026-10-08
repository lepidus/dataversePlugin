import {expect, test} from '@playwright/test';
import {logIn, storageStates} from './support/globalSetup.js';
import {openResearchData} from './support/researchData.js';
import {assignReviewer, configurePlugin, createSubmission, deleteDataset, deleteDeposit} from './support/testData.js';

const DATASET_PUBLISH_SUBMISSION_ACCEPTED = 1;
const isJournal = () => process.env.PKP_APPLICATION === 'ojs2';

test.use({storageState: storageStates.manager});

const createdSubmissions = [];

function createDepositedSubmission(options = {}) {
	const submission = createSubmission('deposited', options);
	createdSubmissions.push(submission);
	return {...submission, persistentUri: submission.persistentId.replace('doi:', 'https://doi.org/')};
}

test.beforeEach(({page}) => {
	page.dashboard = 'editorial';
});

test.afterEach(() => {
	while (createdSubmissions.length) {
		const {id, persistentId} = createdSubmissions.pop();
		deleteDeposit(id);
		deleteDataset(persistentId);
	}
});

async function openDecision(page, submission, decisionName) {
	await page.goto(`index.php/publicknowledge/dashboard/editorial?workflowSubmissionId=${submission.id}`);
	await page.getByRole('button', {name: decisionName}).click();
	await expect(page.getByRole('heading', {level: 1})).toContainText(decisionName);
	await page.getByRole('button', {name: 'Skip this email'}).click();
}

async function recordDecision(page, confirmation) {
	await page.getByRole('button', {name: 'Record Decision'}).click();
	await expect(page.getByText(confirmation)).toBeVisible({timeout: 60000});
}

test('reviewers see only the research data files chosen by the editor', async ({page, browser}) => {
	test.skip(!isJournal(), 'Only journals send submissions for review');
	const submission = createDepositedSubmission();

	await openDecision(page, submission, 'Send for Review');
	await page.getByRole('button', {name: 'Continue'}).click();
	await expect(page.getByText('This submission has deposited research data. Please, select which data files will be made available for reviewers to view')).toBeVisible();
	await expect(page.getByRole('checkbox', {name: 'LEIAME.pdf'})).toBeVisible();
	await page.getByRole('checkbox', {name: 'Planilha_de_dados_ÇÕÔÁÀÃ.json'}).check();
	await recordDecision(page, 'has been sent to the review stage');

	assignReviewer(submission.id, 'jjanssen');
	const reviewerPage = await browser.newPage({storageState: {cookies: [], origins: []}});
	await logIn(reviewerPage, 'jjanssen');
	await reviewerPage.goto(`index.php/publicknowledge/en/reviewer/submission/${submission.id}`);
	await expect(reviewerPage.getByText(`The research data has been submitted to the ${process.env.DATAVERSE_COLLECTION_NAME} repository`)).toBeVisible();
	const researchData = reviewerPage.locator('[id^="datasetReviewGridContainer"]');
	await expect(researchData.getByRole('link', {name: 'Planilha_de_dados_ÇÕÔÁÀÃ.json'})).toBeVisible();
	await expect(researchData.getByText('LEIAME.pdf')).toHaveCount(0);
	await reviewerPage.close();
});

test('declining the submission deletes its research data when the editor chooses to', async ({page}) => {
	const submission = createDepositedSubmission();

	await openDecision(page, submission, 'Decline Submission');
	await expect(page.getByText(`This submission contains deposited research data: ${submission.persistentUri}`)).toBeVisible();
	await expect(page.getByText('Would you like to delete the research data?')).toBeVisible();
	await page.getByRole('radio', {name: 'Yes'}).check();
	await recordDecision(page, 'has been declined and sent to the archives');

	const panel = await openResearchData(page, submission);
	await expect(panel.getByText('No research data transferred.')).toBeVisible();
});

test('accepting the submission publishes its research data when the editor chooses to', async ({page}) => {
	test.skip(!isJournal(), 'Only journals accept submissions');
	test.setTimeout(180000);
	configurePlugin({datasetPublish: DATASET_PUBLISH_SUBMISSION_ACCEPTED});
	try {
		const submission = createDepositedSubmission({stage: 'review'});

		await openDecision(page, submission, 'Accept Submission');
		await page.getByRole('button', {name: 'Continue'}).click();
		const notice = page.getByText(`This submission contains deposited research data that is not yet public: ${submission.persistentUri}`);
		await expect(notice).toContainText('In case you choose to publish them, make sure they are suitable for publication in');
		await expect(page.getByText('Would you like to publish the research data?')).toBeVisible();
		await page.getByRole('radio', {name: 'Yes'}).check();
		await recordDecision(page, 'has been accepted for publication and sent to the copyediting stage');

		await expect(async () => {
			const panel = await openResearchData(page, submission);
			await expect(panel.locator('[data-cy="dataverse-citation"]')).toContainText(/, V1$/);
			await expect(panel.getByText('Draft', {exact: true})).toHaveCount(0, {timeout: 1000});
		}).toPass({timeout: 90000});
	} finally {
		configurePlugin();
	}
});
