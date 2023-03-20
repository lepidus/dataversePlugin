import '../support/commands';

describe('Test research data deposit in dataset tab', function() {
    it('Dataverse Plugin Configuration', function() {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.visit('publicknowledge/management/settings/website#plugins');
        cy.configureDataversePlugin();
    });

    it('Dataset metadata form should not be displayed without research data', function() {
        cy.login('ccorino', null, 'publicknowledge');
        cy.contains('View').click({ force: true });

        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('#datasetTab > form').should('not.exist');
    });

    it('Research data deposited in dataset tab', function() {
        const currentYear = new Date().getFullYear();
        const dataverseServerName = Cypress.env('dataverseServerName');

        cy.login('ccorino', null, 'publicknowledge');
        cy.contains('View').click({ force: true });
        cy.get('button[aria-controls="datasetTab"]').click();

        cy.contains('Add research data').click();
        cy.wait(1000);
        cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
            cy.get('input[type=file]').upload({
                fileContent,
                fileName: 'Data Table.pdf',
                mimeType: 'application/pdf',
                encoding: 'base64',
            });
        });
        cy.wait(200);
        cy.get('input[name="termsOfUse"').check();
        cy.get('[data-modal="fileForm"] form button')
            .contains('Save')
            .click();
        cy.wait(200);
        cy.get('select[id^="datasetMetadata-datasetSubject-control"').select(
            'Other'
        );
        cy.get('#datasetTab form button')
            .contains('Save')
            .click();
        cy.wait(5000);

        cy.get('.pkpHeader__title h1').contains('Research data');
        cy.get('#datasetData > .value > p').contains(
            'Corino, Carlo, ' +
                currentYear +
                ', "The influence of lactation on the quantity and quality of cashmere production"'
        );
        cy.get('#datasetData .value > p > a').contains(
            /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//
        );
        cy.get('#datasetData .value > p').contains(
            ', ' + dataverseServerName + ', DRAFT VERSION'
        );
    });

    it('Check deposit event was registered in activity log', function() {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.findSubmissionAsEditor('admin', 'admin', 'Corino');
        cy.contains('Activity Log').click();
        cy.get('#submissionHistoryGridContainer tbody tr:first td').should(
            ($cell) => {
                expect($cell[1]).to.contain('Carlo Corino');
                expect($cell[2]).to.contain('Research data deposited');
            }
        );
    });
});
