describe('Deposit Draft Dataverse on Submission', function() {

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
			'username': 'icastanheiras1',
			'givenName': 'Íris',
			'familyName': 'Castanheira',
            'email': 'iris@lepidus.com.br',
			'affiliation': 'Preprints da Lepidus',
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

        cy.logout();
    });

});

describe('Publish Draft Dataverse on Submission Publish', function() {

    it('Publish Created Submission', function() {
        var adminUser = Cypress.env('adminUser');
        var adminPassword = Cypress.env('adminPassword');

        cy.login(adminUser, adminPassword);
        cy.get('a:contains(' + adminUser + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('#myQueue a:contains("View")').click();
        cy.wait(1000);
        cy.get('li > .pkpButton').click();
        cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.wait(2000);
        cy.get('.pkpPublication__versionPublished:contains("This version has been posted and can not be edited.")');
    });

    it('Check Publication has Dataset Citation', function() {
        cy.get('a.pkpButton:contains("View")').click();
        cy.waitJQuery();
        cy.get('.label').contains('Data citation');
        cy.get('.value > p').contains('Íris Castanheira, 2021, "The Rise of The Machine Empire"');
    });
});