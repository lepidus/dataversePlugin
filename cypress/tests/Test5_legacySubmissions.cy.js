import '../support/commands.js';

describe('Dataverse Plugin - Legacy submissions', function () {
	let submissionData;

	before(function () {
		submissionData = {
			title: 'The evolution of metro systems in Brazil',
			abstract: 'An example abstract.',
			keywords: ['Metro', 'Brazil'],
		}
	});

	function beginSubmission(submissionData) {
        cy.get('input[name="locale"][value="en"]').click();
        cy.setTinyMceContent('startSubmission-title-control', submissionData.title);
        
        if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
            cy.get('input[name="sectionId"][value="1"]').click();
        }
        
        cy.get('input[name="submissionRequirements"]').check();
        cy.get('input[name="privacyConsent"]').check();
        cy.contains('button', 'Begin Submission').click();
    }

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
		beginSubmission(submissionData);

		cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        submissionData.keywords.forEach(keyword => {
            cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
            cy.wait(500);
            cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
        });
		cy.contains('button', 'Continue').click();

		cy.uploadSubmissionFiles([{
			'file': 'dummy.pdf',
			'fileName': 'dummy.pdf',
			'mimeType': 'application/pdf',
			'genre': 'Article Text'
		}]);
		cy.contains('button', 'Continue').click();
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
        cy.findSubmission('myQueue', submissionData.title);

		cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
		cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
		cy.wait(500);

		cy.contains('button', 'Submit').click();
        cy.get('.modal__panel:visible').within(() => {
            cy.contains('button', 'Submit').click();
        });
        cy.waitJQuery();
        cy.contains('h1', 'Submission complete');
    });
});