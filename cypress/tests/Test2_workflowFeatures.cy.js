import '../support/commands.js';

describe('Dataverse Plugin - Workflow features', function () {
	let submissionTitle = 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation';

    it('Data statement tab is displayed in workflow', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        
        cy.get('#publication-button').click();
        cy.contains('button', 'Data statement').click();

        cy.get('input[name="dataStatementTypes"][value=2]').should('be.checked');
        cy.get('input[name="dataStatementTypes"][value=5]').should('be.checked');
        cy.get('#dataStatement-dataStatementUrls-control')
            .should('have.value', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
        cy.get('#dataStatement-dataStatementReason-control-en').should('have.value', 'Has sensitive data');

        cy.get('input[name="researchDataSubmitted"]').should('be.checked');
        cy.get('input[name="researchDataSubmitted"]').should('be.disabled');
    });
});