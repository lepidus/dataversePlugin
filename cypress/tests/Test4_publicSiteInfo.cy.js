import {
    useDataverseFixtures,
    uniqueSubmission,
    createSubmissionFixture,
    depositDatasetFixture,
    publishDatasetFixture,
    publishSubmissionFixture,
    dataStatementUrl,
} from '../support/fixtures.js';

describe('Dataverse Plugin - Information displayed in public site', function () {
    const fixtures = useDataverseFixtures();

    function preparePublishedArticle(statementTypes) {
        const data = uniqueSubmission({title: 'Public research data', abstract: 'Public article fixture.'});
        return createSubmissionFixture(fixtures, data, {statementTypes}).then((fixture) => {
            depositDatasetFixture(fixture);
            publishDatasetFixture(fixture);
            publishSubmissionFixture(fixture);
            cy.get('.pkpHeader__actions a:contains("View")').invoke('attr', 'href').then((url) => {
                cy.logout();
                cy.visit(url);
            });
            return cy.then(() => data);
        });
    }

    it('Data statement and dataset information are displayed in article landing page', function () {
        preparePublishedArticle([2, 5]).then((data) => {
            cy.contains('h2', 'Data statement');
            cy.contains('The research data is available in one or more data repository(ies)');
            cy.contains('a', dataStatementUrl);
            cy.contains('The research data cannot be made publicly available');
            cy.contains('li', 'Has sensitive data');
            cy.contains('h2', 'Research data');
            cy.contains('"Replication data for: ' + data.title + '"');
            cy.contains('a', /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//);
            cy.contains('Demo Dataverse, V1');
        });
    });

    it('Data statement is not displayed if only submitted to Dataverse', function () {
        preparePublishedArticle([3]).then((data) => {
            cy.contains('h2', 'Data statement').should('not.exist');
            cy.contains('h2', 'Research data');
            cy.contains('"Replication data for: ' + data.title + '"');
            cy.contains('a', /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//);
            cy.contains('Demo Dataverse, V1');
        });
    });
});
