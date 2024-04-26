import '../support/commands.js';

describe('Dataverse Plugin - Submission wizard features', function () {
	let submissionData;

	before(function () {
		submissionData = {
			section: 'Articles',
			title: 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation',
			abstract: 'The transportation industry is the fastest growing energy end-use industry that contributes to greenhouse gases emissions. Mass public transportation can be used as a way to reduce greenhouse gases emissions.',
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
            cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
        });

        cy.contains('h2', 'Data statement');
        cy.get('input[id^="dataStatementTypes"][value=2]').click();
		cy.get('ul[id^="dataStatementUrls"]').should('be.visible');
		cy.contains('button', 'Continue').click();
		cy.contains('It is required to inform the URLs to the data in repositories');

        cy.get('input[id^="dataStatementTypes"][value=2]').click();
		cy.get('ul[id^="dataStatementUrls"]').then((node) => {
			node.tagit('createTag', 'Example text');
		});
		cy.contains('button', 'Continue').click();
		cy.contains('You must only enter the URLs to the data. Other textual information is not accepted.');

        cy.get('input[id^="dataStatementTypes"][value=2]').click();
		cy.get('ul[id^="dataStatementUrls"]').then((node) => {
			node.tagit('createTag', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
		});

        cy.get('input[id^="dataStatementTypes"][value=5]').click();
		cy.get('input[id^="dataStatementReason-en"]').should('be.visible');
		cy.get('button.submitFormButton').click();
		cy.get('label[for^="dataStatementReason"].error').should('contain', 'This field is required');

		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id^="dataStatementReason-en"]').focus().clear().type('Has sensitive data');
    });
});