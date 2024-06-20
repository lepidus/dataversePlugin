import '../support/commands.js';

describe('Dataverse Plugin - Information displayed in public site', function () {
    let firstSubmissionTitle = 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation';
    let secondSubmissionTitle = 'The importance of art for human well-being';

    it('Data statement and dataset information are displayed in article landing page', function () {
        cy.visit('');

        cy.contains('a', 'Archives').click();
        cy.contains('a', 'Vol. 1 No. 2').click();
        cy.contains('a', firstSubmissionTitle).click();

        cy.contains('h2', 'Data statement');
        cy.contains('The research data is available in one or more data repository(ies)');
        cy.contains('a', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
        cy.contains('The research data cannot be made publicly available');
        cy.contains('li', 'Has sensitive data');

        cy.contains('h2', 'Research data');
        cy.contains('"Replication data for: ' + firstSubmissionTitle + '"');
		cy.contains('a', /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//);
		cy.contains('Demo Dataverse, V1');
    });
    it('Data statement is not displayed if only submitted to Dataverse', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('myQueue', secondSubmissionTitle);
        
        cy.get('#workflow-button').click();
        cy.clickDecision('Send To Production');
        cy.recordDecisionSendToProduction(['Elinor Ostrom'], []);
        cy.isActiveStageTab('Production');

        cy.get('#publication-button').click();
        cy.get('div#publication button:contains("Schedule For Publication")').click();
        cy.wait(1000);
        cy.get('select[id="assignToIssue-issueId-control"]').select('1');
        cy.get('div[id^="assign-"] button:contains("Save")').click();
        cy.get('.pkpWorkflow__publishModal button:contains("Publish")').click();
        cy.wait(1000);

        cy.get('.pkpHeader__actions a:contains("View")').click();
        
        cy.contains('h2', 'Data statement').should('not.exist');
        cy.contains('h2', 'Research data');
        cy.contains('"Replication data for: ' + secondSubmissionTitle + '"');
		cy.contains('a', /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//);
		cy.contains('Demo Dataverse, V1');
    });
});