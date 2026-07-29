import '../support/commands.js';

describe('Dataverse Plugin - Legacy submissions', function () {
	let submissionData;

	before(function () {
		submissionData = {
			title: 'The evolution of metro systems in Brazil',
			abstract: 'An example abstract.',
			keywords: ['Metro'],
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

    it('Finishes a submission started while the plugin was disabled', function () {
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
		cy.logout();

		cy.login('ccorino', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();
		beginSubmission(submissionData);

		cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        submissionData.keywords.forEach(keyword => {
			cy.addKeyword('#titleAbstract-keywords-control-en', '#titleAbstract-keywords-selected-en', keyword);
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

        cy.login('dbarnes', null, 'publicknowledge');
		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-dataverseplugin]').check();
        cy.get('input[id^=select-cell-dataverseplugin]').should('be.checked');
		cy.logout();

		cy.login('ccorino', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
		cy.location('search').then((search) => {
			cy.visit('index.php/publicknowledge/submission' + search + '#details');
			cy.reload();
		});

		cy.advanceSubmissionSteps(4);

		cy.contains('It is required to inform the declaration of the data statement');
		
		cy.get('.pkpSteps__step__label:contains("Details")').click();
		cy.get('input[name="dataStatementTypes"][value=1]').click();
		cy.advanceSubmissionSteps(4);

		cy.contains('button', 'Submit').click();
        cy.get('.modal__panel:visible').within(() => {
            cy.contains('button', 'Submit').click();
        });
        cy.waitJQuery();
        cy.contains('h1', 'Submission complete');
    });
});
