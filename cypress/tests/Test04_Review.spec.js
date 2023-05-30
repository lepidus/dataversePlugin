import '../support/commands.js';


describe('Research data on review', function () {
	let submission;

	before(function () {
		submission = {
			id: 0,
			section: 'Articles',
			title: 'The Rise of the Machine Empire',
			abstract: 'An example abstract.',
			keywords: ['Modern History'],
		}
	});

    it('Makes a new submission with research data', function () {
		cy.login('ckwantes', null, 'publicknowledge');

        if (Cypress.env('contextTitles').en_US == 'Journal of Public Knowledge') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
		}
		cy.get('input[id^="dataStatementTypes"][value=3]').click();
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();
		cy.get('button.submitFormButton').click();

        cy.contains('Add research data').click();
		cy.wait(1000);
		cy.fixture('dummy.pdf', { encoding: 'base64' }).then((fileContent) => {
			cy.get('#uploadForm input[type=file]')
				.upload({
					fileContent,
					fileName: 'Data Table.pdf',
					mimeType: 'application/pdf',
					encoding: 'base64',
				});
		});
		cy.wait(200);
        cy.get('input[name="termsOfUse"').check();
        cy.get('#uploadForm button').contains('OK').click();
        cy.get('#submitStep2Form button.submitFormButton').click();

        cy.get('input[id^="title-en_US-"').type(submission.title, { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), submission.abstract);
		});
		cy.get('ul[id^="en_US-keywords-"]').then((node) => {
			submission.keywords.forEach((keyword) => {
				node.tagit('createTag', keyword);
			});
		});
		cy.get('select[id^="datasetSubject"').select('Other');
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

        cy.waitJQuery();
		cy.get('form[id=submitStep4Form]').find('button').contains('Finish Submission').click();
		cy.get('button.pkpModalConfirmButton').click();

		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');

		cy.contains('Review this submission').click();
    });
    it('Send submission to revision stage', function () {
        cy.contains('Assign').click();
        cy.get('select[name="filterUserGroupId"]').select('3');
        cy.get('input[name^="namegrid-users-userselect")').type('ckwantes', { delay: 0 });
        cy.get('td.first_column > input[name="userId"').first().click();
        cy.contains('OK').click();

        cy.contains('Send to Review').click();
        cy.get('#initiateReview').contains('Send to Review').click();

        cy.contains('Add Reviewer').click();
        cy.get('.pkp_modal_panel').get('.pkpSearch__input').type('Revisor name', { delay: 0 });
        cy.contains('Select Reviewer').first().click();
        cy.get('.pkp_modal_panel').contains('Add Reviewer').click();

        cy.logout();
    });
    it('Check research data on review', function () {
        cy.login('reviewer_username', null, 'publicknowledge');
        cy.contains('Submissions').click();
        cy.contains('My Queue').click();
        cy.contains('View').first().click();
        
        cy.contains('Research data');
        cy.contains('Data statement');
        cy.contains('They will be submitted to the');
    });
});