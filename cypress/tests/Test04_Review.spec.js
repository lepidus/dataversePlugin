function addResearchDataFile(filePath, fileName) {
	cy.contains('Add research data').click();
	cy.wait(1000);
	cy.fixture(filePath, { encoding: 'base64' }).then((fileContent) => {
		cy.get('#uploadForm input[type=file]')
			.upload({
				fileContent,
				fileName: fileName,
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
	});
	cy.wait(200);
	cy.get('input[name="termsOfUse"').check();
	cy.get('#uploadForm button').contains('OK').click();
	cy.wait(200);
}

describe('Research data on review', function () {
	let submission;

	before(function () {
		if (Cypress.env('contextTitles').en_US !== 'Journal of Public Knowledge') {
			this.skip();
		}

		submission = {
			id: 0,
			section: 'Articles',
			title: 'Machine Empire and Society',
			abstract: 'An example abstract.',
			keywords: ['Modern History'],
			researchDataFileNames : ['discarded_robots.csv', 'robots_spy_missions.csv']
		}
	});

	it('Makes a new submission with research data', function () {
		cy.login('ccorino', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();

		if (Cypress.env('contextTitles').en_US == 'Journal of Public Knowledge') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
		}
		cy.get('input[id^="dataStatementTypes"][value=3]').click();
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();
		cy.get('#submitStep1Form button.submitFormButton').click();
		
		addResearchDataFile('dummy.pdf', submission.researchDataFileNames[0]);
		addResearchDataFile('dummy.zip', submission.researchDataFileNames[1]);
		addResearchDataFile('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'README.pdf');
		cy.contains(submission.researchDataFileNames[0]);
		cy.contains(submission.researchDataFileNames[1]);
		cy.contains('README.pdf');

		cy.get('#submitStep2Form button.submitFormButton').click();

		cy.get('input[id^="title-en_US-"').type(submission.title, { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), submission.abstract);
		});
		cy.get('ul[id^="en_US-keywords-"]').then((node) => {
			node.tagit('createTag', submission.keywords[0]);
		});

		cy.get('select[id^="datasetSubject"').select('Other');
		cy.get('select[id^="datasetLicense"').select('CC BY 4.0');
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.waitJQuery();
		cy.get('#submitStep4Form button.submitFormButton').click();
		cy.get('button.pkpModalConfirmButton').click();

		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
		cy.contains('Review this submission');

		cy.logout();
	});
	it('Send submission to review stage', function () {
		cy.findSubmissionAsEditor('dbarnes', null, 'Corino');

		cy.get('#editorialActions').contains('Send to Review').click();
		
		cy.get('#selectDataFilesForReview').contains('This submission has deposited research data. Please, select which data files will be made available for reviewers to view.');
		cy.contains('label', submission.researchDataFileNames[1]).within(() => {
			cy.get('input[name^="selectedDataFilesForReview"]').uncheck();
		});

		cy.get('#initiateReview').contains('Send to Review').click();

		cy.waitJQuery();
		cy.contains('Add Reviewer').click();
		cy.contains('Select Reviewer').first().click();
		cy.get('#advancedSearchReviewerForm').contains('Add Reviewer').click();

		cy.logout();
	});
	it('Check research data on review', function () {
		cy.login('agallego', null, 'publicknowledge');
		cy.contains('Submissions').click();
		cy.contains('My Queue').click();
		cy.get(".listPanel__item:visible").first().contains('View').click();

		cy.contains('Data statement');
		cy.contains('The research data has been submitted to the Dataverse de Exemplo Lepidus repository');
		cy.get('a:contains("' + submission.researchDataFileNames[0] + '")');
		cy.get('a:contains("README.pdf")');
	});
});
