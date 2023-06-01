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
		cy.login('ccorino', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();

        if (Cypress.env('contextTitles').en_US == 'Journal of Public Knowledge') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
		}
		cy.get('input[id^="dataStatementTypes"][value=1]').click();
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();
		cy.get('button.submitFormButton').click();

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

		cy.logout();
    });
    it('Send submission to revision stage', function () {
        cy.login('dbarnes', null, 'publicknowledge');
		
		cy.get(".listPanel__item:visible").first().contains('View').click();

		cy.get('#editorialActions').contains('Send to Review').click();
        cy.get('#initiateReview').contains('Send to Review').click();

        cy.contains('Add Reviewer').click();
        cy.get('.pkp_modal_panel').get('.pkpSearch__input').type('Julie Janssen', { delay: 0 });
        cy.contains('Select Reviewer').first().click();
        cy.get('.pkp_modal_panel').contains('Add Reviewer').click();

        cy.logout();
    });
    it('Check research data on review', function () {
        cy.login('jjanssen', null, 'publicknowledge');
        cy.contains('Submissions').click();
        cy.contains('My Queue').click();
        cy.contains('View').first().click();
        
        cy.contains('Data statement');
        cy.contains('Data statement is contained in the manuscript');
        cy.contains('Research data');
    });
});