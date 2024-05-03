import '../support/commands.js';

function advanceNSteps(n) {
    for (let stepsAdvanced = 0; stepsAdvanced < n; stepsAdvanced++) {
        cy.contains('button', 'Continue').click();
        cy.wait(200);
    }
}

describe('Dataverse Plugin - Submission wizard features', function () {
	let submissionData;

	before(function () {
		submissionData = {
			section: 'Articles',
			title: 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation',
			abstract: 'Mass public transportation can be used as a way to reduce greenhouse gases emissions.',
			keywords: [
                'mass public transport',
				'sustainable cities',
                'climate change'
			]
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

    it('Begins submission. Checks for data statement fields', function () {
        cy.login('eostrom', null, 'publicknowledge');
        
        cy.get('div#myQueue a:contains("New Submission")').click();
        beginSubmission(submissionData);

        cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        submissionData.keywords.forEach(keyword => {
            cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
            cy.wait(500);
            cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
        });

        cy.contains('h2', 'Data statement');
        cy.get('#dataStatement-dataStatementUrls-control').should('not.be.visible');
        cy.get('#dataStatement-dataStatementReason-control-en').should('not.be.visible');

        cy.get('input[name="dataStatementTypes"][value=2]').click();
		cy.contains('Insert the URLs to the data');
        cy.get('#dataStatement-dataStatementUrls-control').should('be.visible');
		advanceNSteps(4);
        cy.contains('h3', 'Data statement');
		cy.contains('It is required to inform the URLs to the data in repositories');

        cy.get('.pkpSteps__step__label:contains("Details")').click();
		cy.get('#dataStatement-dataStatementUrls-control').type('Example text');
        cy.get('#dataStatement-dataStatementUrls-control').type('{enter}', {delay: 0});
        cy.contains('This is not a valid URL.');

        cy.get('#dataStatement-dataStatementUrls-control').type('https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
        cy.get('#dataStatement-dataStatementUrls-control').type('{enter}', {delay: 0});
        cy.contains('This is not a valid URL.').should('not.exist');

        cy.get('input[name="dataStatementTypes"][value=5]').click();
        cy.contains('Provide the justification for the unavailability of the data');
		cy.get('#dataStatement-dataStatementReason-control-en').should('be.visible');
		advanceNSteps(4);
		cy.contains('It is required to inform the justification for the unavailability of the data');

		cy.get('.pkpSteps__step__label:contains("Details")').click();
		cy.get('#dataStatement-dataStatementReason-control-en').clear().type('Has sensitive data', {delay: 0});
        advanceNSteps(4);
        
        cy.contains('li', 'The research data is available in one or more data repository(ies)');
        cy.contains('li', ' The research data cannot be made publicly available ');
        cy.contains('a', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
        cy.contains('Has sensitive data');
        cy.get('h3:contains("Data statement")').parent().parent().within(() => {
            cy.get('.pkpNotification--warning').should('not.exist');
        });
    });
    it('Begins submission. Checks for data statement fields', function () {
        cy.login('eostrom', null, 'publicknowledge');

        cy.findSubmission('myQueue', submissionData.title);
        advanceNSteps(1);

        cy.get('h2:contains("Research data")').should('not.be.visible');
        cy.get('.pkpSteps__step__label:contains("Details")').click();
        cy.get('input[name="dataStatementTypes"][value=3]').click();
        advanceNSteps(1);

        cy.contains('h2', 'Research data');
        cy.contains('Use this field only for submitting research data');
        cy.contains('button', 'Add research data');
    });
});