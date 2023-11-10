import '../support/commands.js';

describe('Legacy submissions', function () {
	let submission;

	before(function () {
		submission = {
			id: 0,
			section: 'Articles',
			title: 'The evolution of metro systems in Brazil',
			abstract: 'An example abstract.',
			keywords: ['Metro', 'Brazil'],
		}
	});

    it('Disables plugin temporarily', function () {
        cy.login('dbarnes', null, 'publicknowledge');
		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();
		cy.get('input[id^=select-cell-dataverseplugin]').uncheck();
        cy.get('.pkp_modal_panel:visible').within(() => {
            cy.contains('Are you sure you want to disable this plugin?');
            cy.contains('button', 'OK').click();
        });

        cy.get('input[id^=select-cell-dataverseplugin]').should('not.be.checked');
    });
	it('Starts a new submission, without finishing it', function () {
		cy.login('ccorino', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();

		if (Cypress.env('contextTitles').en_US == 'Journal of Public Knowledge') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
		}
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();
		cy.get('#submitStep1Form button.submitFormButton').click();
		
		cy.get('#submitStep2Form button.submitFormButton').click();

		cy.get('input[id^="title-en_US-"').type(submission.title, { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), submission.abstract);
		});
		cy.get('ul[id^="en_US-keywords-"]').then((node) => {
			for(let keyword of submission.keywords) {
				node.tagit('createTag', keyword);
			}
		});
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.waitJQuery();
		cy.logout();
	});
    it('Enables plugin back', function () {
        cy.login('dbarnes', null, 'publicknowledge');
		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();
		cy.get('input[id^=select-cell-dataverseplugin]').check();
        cy.get('input[id^=select-cell-dataverseplugin]').should('be.checked');
    });
    it('Tries to finish submission', function () {
		cy.login('ccorino', null, 'publicknowledge');
        cy.findSubmission('myQueue', submission.title);

        cy.get('#submitTabs a:contains("2. Upload Submission")').click();
		cy.wait(1000);
        cy.contains('Upload any files the editorial team will need to evaluate your submission.');
        cy.get('#submitStep2Form button.submitFormButton').click();

        cy.get('#submitTabs a:contains("3. Enter Metadata")').click();
		cy.wait(1000);
		cy.get('#datasetSubject').should('not.exist');
        cy.get('#datasetLicense').should('not.exist');
        cy.get('#submitStep3Form button.submitFormButton').click();

        cy.wait(1000);
        cy.get('#submitStep4Form button.submitFormButton').click()
		cy.get('button.pkpModalConfirmButton').click();

        cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
    });
});