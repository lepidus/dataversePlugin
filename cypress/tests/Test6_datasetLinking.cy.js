import '../support/commands.js';

describe('Dataverse Plugin - Dataset linking', function () {
	const currentPersistentId = 'doi:10.5072/FK2/LINKCURRENT';
	const previousPersistentId = 'doi:10.5072/FK2/LINKPREVIOUS';
	const invalidPersistentId = 'doi:10.12345/FK2/BLABLA.TESTE';
	const currentSubmission = {
		title: 'Dataset linking with controlled research data',
		abstract: 'A submission prepared to verify dataset association rules.',
		keywords: ['dataset linking'],
		dataStatementTypes: [3],
		articleFileName: 'dataset-linking-article.pdf',
		datasetLanguage: 'English',
		datasetSubject: 'Earth and Environmental Sciences',
		datasetLicense: 'CC BY 4.0',
		datasetRelationType: 'IsCitedBy',
	};
	const previousSubmission = {
		title: 'Submission already associated with controlled research data',
		abstract: 'A fixture used to verify that datasets cannot be associated twice.',
		keywords: ['existing dataset association'],
		dataStatementTypes: [2],
		dataStatementUrls: ['https://doi.org/10.5072/FK2/LINKPREVIOUS'],
	};
	let currentSubmissionId;

	before(function () {
		cy.startControlledDataverse(currentPersistentId).then((controlledDataverseUrl) => {
			cy.login('dbarnes', null, 'publicknowledge');
			cy.ensureDataversePluginEnabled();
			cy.configureDataverse({
				url: controlledDataverseUrl,
				apiToken: 'valid-token',
				termsOfUse: 'https://example.test/terms',
				datasetPublish: 2,
			});
			cy.logout();
		});

		cy.depositDataverseSubmissionWithApi('eostrom', currentSubmission, [{
			fixture: 'example.json',
			fileName: 'dataset-linking.json',
			mimeType: 'application/json',
			encoding: 'utf8',
		}, {
			fixture: '../../plugins/generic/dataverse/cypress/fixtures/README.pdf',
			fileName: 'README.pdf',
			mimeType: 'application/pdf',
			encoding: 'base64',
		}]);
		cy.get('@submissionId').then((submissionId) => {
			currentSubmissionId = submissionId;
		});

		cy.login('eostrom', null, 'publicknowledge');
		cy.createDataverseSubmissionWithApi(previousSubmission);
		cy.associateDataverseDatasetWithApi(previousPersistentId);
		cy.logout();
	});

	after(function () {
		cy.restoreExternalDataverseConfiguration();
		cy.stopControlledDataverse();
	});

	afterEach(function () {
		cy.clearCookies();
	});

	function accessDatasetTab(username, submissionId) {
		cy.login(username, null, 'publicknowledge');
		const submissionUrl = username === 'eostrom'
			? `/index.php/publicknowledge/authorDashboard/submission/${submissionId}`
			: `/index.php/publicknowledge/workflow/index/${submissionId}/1`;
		cy.visit(submissionUrl);
		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();
	}

	function assertPersistentIdInCitation(persistentId) {
		const persistentUri = persistentId.replace('doi:', 'https://doi.org/');
		cy.get('#datasetData .value a[href*="doi.org"]')
			.should('have.attr', 'href', persistentUri);
	}

	it('Disassociates research data from the submission', function () {
		accessDatasetTab('eostrom', currentSubmissionId);
		cy.contains('button', 'Disassociate').should('not.exist');
		assertPersistentIdInCitation(currentPersistentId);
		cy.clearCookies();

		accessDatasetTab('dbarnes', currentSubmissionId);
		cy.contains('button', 'Disassociate').click();
		cy.get('.modal__panel:visible').within(() => {
			cy.contains('Do you really want to disassociate the research dataset from this submission?');
			cy.contains('The dataset will remain in Dataverse but will no longer be accessible from this submission');
			cy.contains('button', 'Disassociate').click();
		});

		cy.contains('No research data transferred.');
		cy.get('#associateDatasetButton');
	});

	it('Does not associate invalid research data', function () {
		accessDatasetTab('dbarnes', currentSubmissionId);

		cy.get('#associateDatasetButton').click();
		cy.get('.modal__panel:visible').within(() => {
			cy.get('input[name="datasetPersistentId"]').clear().type(previousPersistentId, {delay: 0});
			cy.contains('button', 'Associate').click();
		});
		cy.contains('The dataset entered is already associated with a submission in this context');

		cy.get('.modal__panel:visible').within(() => {
			cy.get('input[name="datasetPersistentId"]').clear().type(invalidPersistentId, {delay: 0});
			cy.contains('button', 'Associate').click();
		});
		cy.contains('The dataset entered is not present at the Dataverse repository');
	});

	it('Re-associates research data to the submission using its persistent id', function () {
		accessDatasetTab('eostrom', currentSubmissionId);
		cy.get('#associateDatasetButton').should('not.exist');
		cy.clearCookies();

		accessDatasetTab('dbarnes', currentSubmissionId);
		cy.get('#associateDatasetButton').click();
		cy.get('.modal__panel:visible').within(() => {
			cy.get('input[name="datasetPersistentId"]').type(currentPersistentId, {delay: 0});
			cy.contains('button', 'Associate').click();
		});

		cy.contains('h1', 'Research data');
		assertPersistentIdInCitation(currentPersistentId);
		cy.contains('button', 'Disassociate');
	});
});
