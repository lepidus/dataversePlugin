import '../support/commands';

describe('Test research data state features', function() {
    it('Dataverse Plugin Configuration', function() {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.visit('publicknowledge/management/settings/website#plugins');
        cy.configureDataversePlugin();
    });

    it('Check research data state field is required', function() {
        cy.login('ccorino', null, 'publicknowledge');
        cy.contains('New Submission').click();

        cy.get('input[id^="checklist-"]').click({ multiple: true });
        cy.get('input[id=privacyConsent]').click();
        cy.get('button.submitFormButton').click();

        cy.contains(
            'It is required to inform the status of the research data.'
        );
    });

    it('Check research data url field is required', function() {
        cy.get('input[id^="researchData-repoAvailable"]').click();
        cy.get('button.submitFormButton').click();

        cy.get('label[for^="researchDataUrl"]').should(
            'contain',
            'This field is required'
        );
    });

    it('Check research data reason field is required', function() {
        cy.get('input[id^="researchData-private"]').click();
        cy.get('button.submitFormButton').click();

        cy.get('label[for^="researchDataReason"]').should(
            'contain',
            'This field is required'
        );
    });

    it('Display file grid if deposit option is selected', function() {
        cy.login('ccorino', null, 'publicknowledge');
        cy.contains('New Submission').click();

        cy.get('input[id^="researchData-submissionDeposit"]').click();

        cy.get('input[id^="checklist-"]').click({ multiple: true });
        cy.get('input[id=privacyConsent]').click();
        cy.get('button.submitFormButton').click();
    });

    it('Check research data state is displayed in dataset tab', function() {
        cy.login('ccorino', null, 'publicknowledge');
        cy.contains('New Submission').click();

        cy.get('input[id^="researchData-private"]').click();
        cy.get('input[id^="researchDataReason"]').type('Sensitive data', {
            delay: 0,
        });

        cy.get('input[id^="checklist-"]').click({ multiple: true });
        cy.get('input[id=privacyConsent]').click();
        cy.get('button.submitFormButton').click();

        cy.get('#submitStep2Form button.submitFormButton').click();

        cy.get(
            'input[id^="title-en_US-"'
        ).type(
            'The Power of Positive Thinking: How to Cultivate a Positive Mindset',
            { delay: 0 }
        );
        cy.get('label')
            .contains('Title')
            .click();
        cy.get('textarea[id^="abstract-en_US-"').then((node) => {
            cy.setTinyMceContent(node.attr('id'), 'Abstract of the submission');
        });

        cy.waitJQuery();
        cy.get(
            'form[id=submitStep3Form] button:contains("Save and continue"):visible'
        ).click();

        cy.wait(3000);
        cy.get('form[id=submitStep4Form]')
            .find('button')
            .contains('Finish Submission')
            .click();
        cy.get('button.pkpModalConfirmButton').click();
        cy.waitJQuery();
        cy.get('h2:contains("Submission complete")');

        cy.contains('Review this submission').click();

        cy.get('button[aria-controls="datasetTab"]').click();
        cy.contains('The research data cannot be made publicly available.');
        cy.contains('Justification: Sensitive data');
    });
});
