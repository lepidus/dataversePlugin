import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');
const serverPath = Cypress.env('serverPath') || 'publicknowledge';

var submissionId = null;

describe('Defines a subject to submission dataset', function() {
    it('Dataverse Plugin Configuration', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a:contains(' + adminUser + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.configureDataversePlugin();
    });

    it('Check subject field is visible when submission has research data', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a')
            .contains(adminUser)
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.get(
            'a:contains("Make a New Submission"), div#myQueue a:contains("New Submission")'
        ).click();

        cy.get('input[id^="researchData-submissionDeposit"]').click();
        cy.get('input[id^="checklist-"]').click({ multiple: true });
        cy.get('input[id=privacyConsent]').click();
        cy.get('input[name=userGroupId]')
            .parent()
            .contains('Preprint Server manager')
            .click();
        cy.get('button.submitFormButton').click();

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
        cy.get('#uploadForm button')
            .contains('OK')
            .click();
        cy.wait(200);

        cy.location('search').then((search) => {
            submissionId = parseInt(search.split('=')[1], 10);
        });

        cy.waitJQuery();
        cy.get('#submitStep2Form button.submitFormButton').click();

        cy.get('select[id^="datasetSubject"').should('be.visible');
    });

    it('Check subject field is not visible when submission not has research data', function() {
        cy.login(adminUser, adminPassword);
        cy.visit(
            'index.php/' +
                serverPath +
                '/submission/wizard/2?submissionId=' +
                submissionId +
                '#step-2'
        );
        cy.get('a[name="step-2"]').click();
        cy.get('tr:contains(Data Table.pdf) .show_extras').click();
        cy.get('a')
            .contains('Delete')
            .click();
        cy.get('[aria-label="Delete"] .pkpModalConfirmButton').click();
        cy.waitJQuery();
        cy.get('button.submitFormButton').click();

        cy.get('select[id^="datasetSubject"').should('not.be.visible');
    });
});
