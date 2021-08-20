describe('Deposit Atom Entry', function() {

    it('Dataverse Plugin Configuration', function() {
        var adminUser = Cypress.env('adminUser');
        var adminPassword = Cypress.env('adminPassword');

        cy.login(adminUser, adminPassword);
        cy.get('a:contains(' + adminUser + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
        cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]').check();
        cy.wait(2000);
        cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]').should('be.checked');
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras').click();
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)').click();
        cy.get('input[name="dataverseServer"]').invoke('val', Cypress.env('dataverseServerURI'));
        cy.get('input[name="dataverse"]').invoke('val', Cypress.env('dataverseURI'));
        cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
        cy.get('form[id="dataverseAuthForm"] button[name="submitFormButton"]').click();
        cy.get('div:contains(\'Your changes have been saved.\')');
        cy.logout();
    });

    it('Create Submission', function() {

		cy.register({
			'username': Cypress.env('authorUser'),
			'givenName': Cypress.env('authorGivenName'),
			'familyName': Cypress.env('authorFamilyName'),
			'affiliation': Cypress.env('authorAffiliation'),
			'country': 'Brazil'
		});

		cy.createSubmission({
			'title': 'The Rise of The Machine Empire',
			'abstract': 'An example abstract',
			'keywords': [
				'Modern History'
			],
            'files': [{
                'file': 'dummy.pdf',
                'fileName': 'Data Table.pdf',
                'fileTitle': 'Data Table',
                'genre': 'Data Set'
            }]
		});
    });

});