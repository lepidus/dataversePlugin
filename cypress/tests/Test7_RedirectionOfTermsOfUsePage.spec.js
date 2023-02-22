import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');

describe('View Dataverse Terms of Use page', function() {
	it('Dataverse Plugin Configuration', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.configureDataversePlugin();
	});

	it('Check user is redirected to terms of use page', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a').contains(adminUser).click();
		cy.get('a').contains('Dashboard').click();
		cy.get('a:contains("Make a New Submission"), div#myQueue a:contains("New Submission")').click();

		cy.get('input[id^="checklist-"]').click({multiple: true});
		cy.get('input[id=privacyConsent]').click();
		cy.get('input[name=userGroupId]')
			.parent()
			.contains('Preprint Server manager')
			.click();
		cy.get('button.submitFormButton').click();

        cy.get('label a:contains(Terms of Use)').then(termsOfUse => {
            expect(termsOfUse).to.have.attr('href', Cypress.env('dataverseTermsOfUse'));
        });
	});
});
