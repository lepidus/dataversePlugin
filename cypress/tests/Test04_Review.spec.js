function addResearchDataFile(fileName) {
	cy.wait(1000);
	cy.fixture('dummy.pdf', { encoding: 'base64' }).then((fileContent) => {
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
}

describe('Research data on review', function () {
	let submission;
	let dataverseServerName;

	before(function () {
		if (Cypress.env('contextTitles').en_US !== 'Journal of Public Knowledge') {
			this.skip();
		}

		submission = {
			id: 0,
			section: 'Articles',
			title: 'The Rise of the Machine Empire',
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

		cy.contains('Add research data').click();
		
		addResearchDataFile(sumbission.researchDataFileNames[0]);
		addResearchDataFile(sumbission.researchDataFileNames[1]);
		cy.get('#submitStep2Form button.submitFormButton').click();

		cy.get('input[id^="title-en_US-"').type(submission.title, { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), submission.abstract);
		});
		cy.get('ul[id^="en_US-keywords-"]').then((node) => {
			node.tagit('createTag', submission.keywords[0]);
		});
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.waitJQuery();
		cy.get('#submitStep4Form button.submitFormButton').click();
		cy.get('button.pkpModalConfirmButton').click();

		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
		cy.contains('Review this submission').click();
		
		cy.get('button[aria-controls="publication"]').click();
		cy.get('#datasetData .value p').then((citation) => {
			dataverseServerName = citation.text().split(',')[5].trim();
		});

		cy.logout();
	});
	it('Send submission to review stage', function () {
		cy.findSubmissionAsEditor('dbarnes', null, 'Corino');

		cy.get('#editorialActions').contains('Send to Review').click();
		
		cy.get('#editorialActions').contains('This submission has deposited research data. Please, select which data files will be made available for reviewers to view.');
		cy.get('input[name="selectDataFilesForReview"]').should('be.checked');
		cy.get('input[name="selectDataFilesForReview"]').eq(1).uncheck();

		cy.get('#initiateReview').contains('Send to Review').click();

		cy.contains('Add Reviewer').click();
		cy.get(".listPanel__item").first().contains('Select Reviewer').click();
		cy.get('#advancedSearchReviewerForm').contains('Add Reviewer').click();

		cy.logout();
	});
	it('Check research data on review', function () {
		cy.login('agallego', null, 'publicknowledge');
		cy.contains('Submissions').click();
		cy.contains('My Queue').click();
		cy.get(".listPanel__item:visible").first().contains('View').click();

		cy.contains('Data statement');
		cy.contains('Research data has been submitted to the ' + dataverseName + ' repository');
		cy.get('a:contains("' + submission.researchDataFileNames[0] + '")');
	});
});
