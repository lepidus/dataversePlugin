describe('Check form operation', function() {

    var admin = Cypress.env('adminUser');
	var adminPassword = Cypress.env('adminPassword');

    it('Configuration Performed', function() {

        cy.login(admin, adminPassword);
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
		cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras').click();
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)').click();
        cy.get('input[name="dvnUri"]').invoke('val', Cypress.env('dataverseURI'));
        cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
        cy.get('form[id="dataverseAuthForm"] button[name="submitFormButton"]').click();
        cy.get('div:contains(\'Your changes have been saved.\')');
        cy.logout();
    });

    it('Dataverse URL not filled', function() {

        cy.login(admin, adminPassword);
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
		cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras').click();
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)').click();
        cy.get('input[name="dvnUri"]').invoke('val', '');
        cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
        cy.get('form[id="dataverseAuthForm"] button[name="submitFormButton"]').click();
        cy.contains('This field is required.');
        cy.logout();
    });

    it('Invalid URL', function() {

        cy.login(admin, adminPassword);
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
		cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras').click();
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)').click();
        cy.get('input[name="dvnUri"]').invoke('val', 'thisIsNotaURL');
        cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
        cy.get('form[id="dataverseAuthForm"] button[name="submitFormButton"]').click();
        cy.contains('Please enter a valid URL.');
        cy.logout();
    });

    it('API Token not filled', function() {

        cy.login(admin, adminPassword);
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
		cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras').click();
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)').click();
        cy.get('input[name="dvnUri"]').invoke('val', Cypress.env('dataverseURI'));
        cy.get('input[name="apiToken"]').invoke('val', '');
        cy.get('form[id="dataverseAuthForm"] button[name="submitFormButton"]').click();
        cy.contains('This field is required.');
        cy.logout();
    });

    it('Invalid credentials', function() {

        cy.login(admin, adminPassword);
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
		cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras').click();
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)').click();
        cy.get('input[name="dvnUri"]').invoke('val', Cypress.env('dataverseURI'));
        cy.get('input[name="apiToken"]').invoke('val', 'wrongToken');
        cy.get('form[id="dataverseAuthForm"] button[name="submitFormButton"]').click();
        cy.contains('Can\'t connect to Dataverse');
        cy.logout();
    });

});