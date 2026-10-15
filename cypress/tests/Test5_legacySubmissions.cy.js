import '../support/commands.js';

describe('Dataverse Plugin - Legacy submissions', function () {
	let submissionData;

	before(function () {
		submissionData = {
			title: `The evolution of metro systems in Brazil ${Date.now()}`,
			abstract: 'An example abstract.',
			keywords: ['Metro', 'Brazil'],
		}
	});

    afterEach(function () {
        // A failure while the plugin is disabled must not affect the next spec.
        cy.loginDataverseUser('dbarnes');
        cy.contains('a', 'Website').click();
        cy.get('#plugins-button').click();
        cy.setDataversePluginEnabled(true);
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

    it('requires a data statement when resuming a submission started before plugin activation', function () {
        cy.configureDataverseForTests();

        // Start a submission while the plugin is disabled.
        cy.loginDataverseUser('dbarnes');
		cy.contains('a', 'Website').click();

		cy.get('#plugins-button').click();
		cy.setDataversePluginEnabled(false);

        cy.get('input[id^=select-cell-dataverseplugin]').should('not.be.checked');

        // Save the legacy draft before enabling the plugin.
		cy.loginDataverseUser('ccorino');

		cy.get('div#myQueue a:contains("New Submission")').click();
		beginSubmission(submissionData);

		cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        submissionData.keywords.forEach(keyword => {
            cy.addDataverseKeyword('#titleAbstract-keywords-control-en', keyword);
        });
		cy.advanceSubmissionSteps();

		cy.uploadSubmissionFiles([{
			'file': 'dummy.pdf',
			'fileName': 'dummy.pdf',
			'mimeType': 'application/pdf',
			'genre': 'Article Text'
		}]);
		cy.advanceSubmissionSteps();
		cy.logout();

        // Resume the same draft after activation.
        cy.loginDataverseUser('dbarnes');
		cy.contains('a', 'Website').click();

		cy.get('#plugins-button').click();
		cy.setDataversePluginEnabled(true);
        cy.get('input[id^=select-cell-dataverseplugin]').should('be.checked');

        // Validate and complete the legacy submission.
		cy.loginDataverseUser('ccorino');
        cy.findSubmission('myQueue', submissionData.title);

        cy.advanceSubmissionSteps(4);

		cy.contains('It is required to inform the declaration of the data statement');

		cy.get('.pkpSteps__step__label:contains("Details")').click();
		cy.get('input[name="dataStatementTypes"][value=1]').click();
        cy.advanceSubmissionSteps(4);

        cy.submitResearchData();
    });
});
