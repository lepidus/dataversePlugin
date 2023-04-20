import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');

describe('Test research data upload features', function() {
    it('Dataverse Plugin Configuration', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a:contains(' + adminUser + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.configureDataversePlugin();
    });

    it('Check terms of use', function() {
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
            cy.get('input[type=file]')
                .upload({
                    fileContent,
                    fileName: 'Data Table.pdf',
                    mimeType: 'application/pdf',
                    encoding: 'base64',
                })
                .as('fileUpload');
        });
        cy.wait(200);
        cy.get('label a:contains(Terms of Use)').then((termsOfUse) => {
            expect(termsOfUse).to.have.attr(
                'href',
                Cypress.env('dataverseTermsOfUse')
            );
        });
        cy.get('#uploadForm button')
            .contains('OK')
            .click();

        cy.get('label[for="termsOfUse"]').should(
            'contain',
            'This field is required'
        );
    });

    it('Check galley contains research data', function() {
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

        cy.get('a:contains("Add galley")').click();
        cy.wait(200);
        cy.get('div.pkp_modal_panel').then(($modalDiv) => {
            cy.wait(300);
            if (
                $modalDiv.find('div.header:contains("Create New Galley")')
                    .length
            ) {
                cy.get('div.pkp_modal_panel input[id^="label-"]').type('PDF', {
                    delay: 0,
                });
                cy.get('div.pkp_modal_panel button:contains("Save")').click();
                cy.wait(200);
            }
        });
        cy.get('select[id=genreId]').select('Preprint Text');
        cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
            cy.get('input[type=file]').upload({
                fileContent,
                fileName: 'Data Table.pdf',
                mimeType: 'application/pdf',
                encoding: 'base64',
            });
        });
        cy.get('button')
            .contains('Continue')
            .click();
        cy.wait(200);
        cy.get('button')
            .contains('Continue')
            .click();
        cy.get('button')
            .contains('Complete')
            .click();

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

        cy.get('#submitStep2Form button.submitFormButton').click();

        cy.get('#submitStep2FormNotification').should(
            'contain',
            'Research data and galley have the same file. Make sure the files are added in the proper section.'
        );
    });
});
