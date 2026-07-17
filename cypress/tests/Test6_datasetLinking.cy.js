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

	function disassociateResearchData() {
		cy.contains('button', 'Disassociate research data').click();
		cy.get('.modal__panel:visible, [role="dialog"]:visible').within(() => {
			cy.contains('button', /Disassociate research data|Disassociate/).click();
		});
		cy.wait(1000);
	}

	function associateResearchData(persistentId) {
		cy.contains('button', 'Associate research data').click();
		cy.get('.modal__panel:visible, [role="dialog"]:visible').within(() => {
			cy.get(
				'input[name="persistentId"], ' +
				'input[name="datasetPersistentId"], ' +
				'input[name="datasetPersistentID"], ' +
				'input[id*="persistentId"], ' +
				'input[id*="PersistentId"]'
			).focus().clear().type(persistentId, {delay: 0});
			cy.contains('button', /Associate research data|Associate/).click();
		});
	}

	function closeVisibleModal() {
		cy.get('.modal__panel:visible, [role="dialog"]:visible').within(() => {
			cy.contains('button', /Cancel|Close/).click();
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
        cy.contains('button', 'Disassociate research data').should('not.exist');
		
        getPersistentIdFromCitation().then((persistentId) => {
			currentDatasetPersistentId = persistentId;
		});
		cy.logout();

        accessDatasetTab(submissionData.title, 'dbarnes');
		disassociateResearchData();

		cy.contains('No research data transferred.');
		cy.contains('button', 'Associate research data');
	});

	it('Does not associate invalid research data', function () {
        accessDatasetTab(submissionWithResearchData, 'dbarnes', 'archive');
		getPersistentIdFromCitation().then((persistentId) => {
			previousDatasetPersistentId = persistentId;
		});
		cy.logout();

        accessEmptyDatasetTab(submissionData.title, 'dbarnes');
        associateResearchData(previousDatasetPersistentId);
		cy.get('.modal__panel:visible, [role="dialog"]:visible')
			.contains(/already associated|already linked/i);
		closeVisibleModal();

		associateResearchData('doi:10.12345/FK2/BLABLA.TESTE');
		cy.get('.modal__panel:visible, [role="dialog"]:visible')
			.contains(/does not exist|not found|not possible/i);
		closeVisibleModal();
	});

	it('Associates research data to the submission using its persistent id', function () {
        accessEmptyDatasetTab(submissionData.title, 'eostrom');
		cy.contains('button', 'Associate research data').should('not.exist');
		cy.logout();

        accessEmptyDatasetTab(submissionData.title, 'dbarnes');
        associateResearchData(currentDatasetPersistentId);
		cy.wait(1000);

		cy.contains('h1', 'Research data', {timeout: 10000});
		cy.contains('a', currentDatasetPersistentId.replace('doi:', 'https://doi.org/'));
		cy.contains('button', 'Disassociate research data');
	});
});
