import '../support/commands.js';

describe('Dataverse Plugin - Dataset linking', function () {
	let submissionData;
	let currentDatasetPersistentId;
	let previousSubmission;
	let previousDatasetPersistentId;

	before(function () {
		submissionData = {
			title: 'Mayday: The importance of containment plans in disaster management',
			abstract: 'Containment plans are essential to manage disasters when they happen.',
			keywords: [
				'containment plans',
				'disaster management',
				'disasters'
			]
		}
		previousSubmission = 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation';
	});

	function advanceNSteps(n) {
		for (let stepsAdvanced = 0; stepsAdvanced < n; stepsAdvanced++) {
			cy.contains('button', 'Continue').click();
			cy.wait(200);
		}
	}

	function beginSubmission(submission) {
		cy.get('input[name="locale"][value="en"]').click();
		cy.setTinyMceContent('startSubmission-title-control', submission.title);

		if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
			cy.get('input[name="sectionId"][value="1"]').click();
		}

		cy.get('input[name="submissionRequirements"]').check();
		cy.get('input[name="privacyConsent"]').check();
		cy.contains('button', 'Begin Submission').click();
	}

	function uploadDatasetFile(file, fileName, mimeType, encoding) {
		cy.contains('button', 'Add research data').click();
		cy.fixture(file, encoding).then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName,
				mimeType,
				encoding,
			});
		});
		cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
	}

	function accessDatasetTab(submissionTitle, username, tab = 'active') {
		cy.login(username, null, 'publicknowledge');
		cy.findSubmission(tab, submissionTitle);
		cy.waitDatasetTabLoading();
		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();
	}

	function accessEmptyDatasetTab(submissionTitle, username, tab = 'active') {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.findSubmission(tab, submissionTitle);
		cy.waitDataStatementTabLoading();
		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();
	}

	function getPersistentIdFromCitation() {
		return cy.get('#datasetData .value a[href*="doi.org"]').invoke('attr', 'href').then((persistentUri) => {
			expect(persistentUri).to.match(/^https:\/\/doi\.org\/10\.[^/]+\/FK2\/[^/]+$/);
			return persistentUri.replace('https://doi.org/', 'doi:');
		});
	}

	it('Author creates a submission with research data', function () {
		cy.login('eostrom', null, 'publicknowledge');
		cy.get('div#myQueue a:contains("New Submission")').click();
		beginSubmission(submissionData);

		cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
		submissionData.keywords.forEach(keyword => {
			cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
			cy.wait(500);
			cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
		});
		cy.get('input[name="dataStatementTypes"][value=3]').click();
		advanceNSteps(1);

		cy.uploadSubmissionFiles([{
			'file': 'dummy.pdf',
			'fileName': 'dummy.pdf',
			'mimeType': 'application/pdf',
			'genre': 'Article Text'
		}]);
		uploadDatasetFile(
            'example.json',
            'mayday-dataset.json',
            'application/json',
            'utf8'
        );
		uploadDatasetFile(
            '../../plugins/generic/dataverse/cypress/fixtures/README.pdf',
            'README.pdf',
            'application/pdf',
            'base64'
        );
		advanceNSteps(3);

		cy.get('select[name="datasetLanguage"]').select('English');
		cy.get('select[name="datasetSubject"]').select('Earth and Environmental Sciences');
		cy.get('select[name="datasetLicense"]').select('CC BY 4.0');
		cy.get('select[name="datasetRelationType"]').select('Is Cited By');
		advanceNSteps(1);

		cy.contains('button', 'Submit').click();
		cy.get('.modal__panel:visible').within(() => {
			cy.contains('button', 'Submit').click();
		});
		cy.wait(7000);

        cy.contains('h1', 'Submission complete');
	});

	it('Disassociates research data from the submission', function () {
        accessDatasetTab(submissionData.title, 'eostrom');
        cy.contains('button', 'Disassociate').should('not.exist');
		
        getPersistentIdFromCitation().then((persistentId) => {
			currentDatasetPersistentId = persistentId;
		});
		cy.logout();

        accessDatasetTab(submissionData.title, 'dbarnes');
		cy.contains('button', 'Disassociate').click();
		cy.get('.modal__panel:visible, [role="dialog"]:visible').within(() => {
			cy.contains('Do you really want to disassociate the research dataset from this submission?');
			cy.contains('The dataset will remain in Dataverse but will no longer be accessible from this submission');
			cy.contains('button', 'Disassociate').click();
		});

		cy.contains('No research data transferred.');
		cy.contains('button', 'Associate dataset');
	});

	it('Does not associate invalid research data', function () {
        accessDatasetTab(submissionWithResearchData, 'dbarnes', 'archive');
		getPersistentIdFromCitation().then((persistentId) => {
			previousDatasetPersistentId = persistentId;
		});
		cy.logout();

        accessEmptyDatasetTab(submissionData.title, 'dbarnes');

		cy.contains('button', 'Associate dataset').click();
		cy.get('.modal__panel:visible, [role="dialog"]:visible').within(() => {
			cy.get('input[name="datasetPersistentId"]').type(previousDatasetPersistentId, {delay: 0});
			cy.contains('button', 'Associate').click();
		});
		cy.contains('The dataset entered is already associated with a submission in this context');

		cy.get('.modal__panel:visible, [role="dialog"]:visible').within(() => {
			cy.get('input[name="datasetPersistentId"]').type('doi:10.12345/FK2/BLABLA.TESTE', {delay: 0});
			cy.contains('button', 'Associate').click();
		});
		cy.contains('The dataset entered is not present at the Dataverse repository');
	});

	it('Re-associates research data to the submission using its persistent id', function () {
        accessEmptyDatasetTab(submissionData.title, 'eostrom');
		cy.contains('button', 'Associate dataset').should('not.exist');
		cy.logout();

        accessEmptyDatasetTab(submissionData.title, 'dbarnes');
		cy.contains('button', 'Associate dataset').click();
		cy.get('.modal__panel:visible, [role="dialog"]:visible').within(() => {
			cy.get('input[name="datasetPersistentId"]').type(currentDatasetPersistentId, {delay: 0});
			cy.contains('button', 'Associate').click();
		});

		cy.waitDatasetTabLoading();
		cy.contains('h1', 'Research data');
		cy.contains('a', currentDatasetPersistentId.replace('doi:', 'https://doi.org/'));
		cy.contains('button', 'Disassociate');
	});
});
