import '../support/commands.js';

describe('Dataverse Plugin - Information displayed in public site', function () {
    let submissionTitle = 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation';

    it('Data statement and dataset information are displayed in article landing page', function () {
        cy.visit('');

        cy.contains('a', 'Archives').click();
        cy.contains('a', 'Vol. 1 No. 2').click();
        cy.contains('a', submissionTitle).click();

        cy.contains('h2', 'Data statement');
        cy.contains('The research data is available in one or more data repository(ies)');
        cy.contains('a', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
        cy.contains('The research data cannot be made publicly available');
        cy.contains('li', 'Has sensitive data');

        cy.contains('h2', 'Research data');
        cy.contains('Kwantes, Catherine, ' + currentYear + ', "Replication data for: ' + submission.title + '"');
		cy.contains('a', /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//);
		cy.contains('Demo Dataverse, V1');
    });
});