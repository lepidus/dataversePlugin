import '../support/commands.js';

describe('Dataverse Plugin - Dataset linking', function () {
	let submissionData;
	let currentDatasetPersistentId;
	let previousSubmission;
	let previousDatasetPersistentId;

	before(function () {
		submissionData = {
			section: 'Articles',
            title: 'Mayday: The importance of containment plans in disaster management',
			abstract: 'Containment plans are essential to manage disasters when they happen.',
			keywords: [
				'containment plans',
				'disaster management',
				'disasters'
			]
		}
		previousSubmission = 'The Rise of the Machine Empire';
	});

	afterEach(() => {
        cy.logout();
    });

	function submissionFirstStep(submission) {
		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
		}
		cy.get('input[id^="dataStatementTypes"][value=3]').click();
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.contains('label', 'Yes, I agree to have my data collected').within(() => {
			cy.get('input').check();
		});
		cy.get('#submitStep1Form button.submitFormButton').click();
	}

	function uploadDatasetFile(file, fileName, mimeType) {
		cy.contains('Add research data').click();
		cy.wait(1000);
		cy.fixture(file, { encoding: 'base64' }).then((fileContent) => {
			cy.get('#uploadForm input[type=file]')
				.upload({
					fileContent,
					fileName: fileName,
					mimeType: mimeType,
					encoding: 'base64',
				});
		});
		cy.get('input[name="termsOfUse"').check();
		cy.get('#uploadForm button').contains('OK').click();
		cy.wait(1000);
	}

    function submissionThirdStep(submission) {
        cy.get('input[id^="title-en_US-"').type(submission.title, { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), submission.abstract);
		});
		cy.get('ul[id^="en_US-keywords-"]').then((node) => {
			node.tagit('createTag', submission.keywords[0]);
		});
        cy.get('select[name="datasetLanguage"]').select('English');
		cy.get('select[name="datasetSubject"]').select('Earth and Environmental Sciences');
		cy.get('select[name="datasetLicense"]').select('CC BY 4.0');
		cy.get('select[name="datasetRelationType"]').select('Is Cited By');
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();
    }

	function accessDatasetTab(submissionTitle, username, tab = 'active') {
		cy.login(username, null, 'publicknowledge');
		cy.findSubmission(tab, submissionTitle);
		cy.waitDatasetTabLoading('datasetTab');
	}

	function accessEmptyDatasetTab(submissionTitle, username, tab = 'active') {
		cy.login(username, null, 'publicknowledge');
		cy.findSubmission(tab, submissionTitle);
		cy.waitDataStatementTabLoading();
		cy.get('#datasetTab-button').click();
	}

	function convertPersistentUriToId(persistentUri) {
		var doiUrlPrefix = 'https://doi.org/';

		if (persistentUri.indexOf(doiUrlPrefix) === 0) {
			return 'doi:' + persistentUri.substring(doiUrlPrefix.length);
		}

		return persistentUri;
	}

	function getPersistentIdFromCitation() {
		return cy.get('#datasetData .value a[href*="doi.org"]').invoke('attr', 'href').then((persistentUri) => {
			return convertPersistentUriToId(persistentUri);
		});
	}

	it('Author creates a submission with research data', function () {
		cy.login('eostrom', null, 'publicknowledge');

        cy.get('div#myQueue a:contains("New Submission")').click();
		submissionFirstStep(submissionData);
		uploadDatasetFile(
            'dummy.pdf',
            'Data Table.pdf',
            'application/pdf'
        );
		uploadDatasetFile(
            '../../plugins/generic/dataverse/cypress/fixtures/README.pdf',
            'README.pdf',
            'application/pdf'
        );
        cy.get('#submitStep2Form button.submitFormButton').click();
        submissionThirdStep(submissionData);

        cy.waitJQuery();
		cy.get('#submitStep4Form button.submitFormButton').click();
		cy.get('button.pkpModalConfirmButton').click();
		cy.wait(7000);

		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
	});

	it('Disassociates research data from the submission', function () {
        accessDatasetTab(submissionData.title, 'eostrom', 'myQueue');
        cy.contains('button', 'Disassociate').should('not.exist');
		
        getPersistentIdFromCitation().then((persistentId) => {
			currentDatasetPersistentId = persistentId;
		});
		cy.logout();

        accessDatasetTab(submissionData.title, 'dbarnes');
		cy.contains('button', 'Disassociate').click();
		cy.get('div[data-modal="disassociateDataset"]').within(() => {
			cy.contains('Do you really want to disassociate the research dataset from this submission?');
			cy.contains('The dataset will remain in Dataverse but will no longer be accessible from this submission');
			cy.contains('button', 'Disassociate').click();
		});

		cy.contains('No research data transferred.');
		cy.get('#associateDatasetButton');
	});

	it('Does not associate invalid research data', function () {
        accessDatasetTab(previousSubmission, 'dbarnes', 'archive');
		getPersistentIdFromCitation().then((persistentId) => {
			previousDatasetPersistentId = persistentId;
		});
		cy.logout();

        accessEmptyDatasetTab(submissionData.title, 'dbarnes');

		cy.get('#associateDatasetButton').click();
		cy.get('div[data-modal="associateResearchData"]').within(() => {
			cy.get('input[name="datasetPersistentId"]').clear().type(previousDatasetPersistentId, {delay: 0});
			cy.contains('button', 'Associate').click();
		});
		cy.contains('The dataset entered is already associated with a submission in this context');

		cy.get('div[data-modal="associateResearchData"]').within(() => {
			cy.get('input[name="datasetPersistentId"]').clear().type('doi:10.12345/FK2/BLABLA.TESTE', {delay: 0});
			cy.contains('button', 'Associate').click();
		});
		cy.contains('The dataset entered is not present at the Dataverse repository');
	});

	it('Re-associates research data to the submission using its persistent id', function () {
        accessEmptyDatasetTab(submissionData.title, 'eostrom', 'myQueue');
		cy.get('#associateDatasetButton').should('not.exist');
		cy.logout();

        accessEmptyDatasetTab(submissionData.title, 'dbarnes');
		cy.get('#associateDatasetButton').click();
		cy.get('div[data-modal="associateResearchData"]').within(() => {
			cy.get('input[name="datasetPersistentId"]').type(currentDatasetPersistentId, {delay: 0});
			cy.contains('button', 'Associate').click();
			cy.wait(500);
		});

		cy.waitDatasetTabLoading();
		cy.contains('h1', 'Research data');
		cy.contains('button', 'Disassociate');
	});
});
