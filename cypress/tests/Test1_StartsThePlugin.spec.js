describe('Starts the plugin', function() {

    var admin = Cypress.env('adminUser');
	var adminPassword = Cypress.env('adminPassword');

    it('Activate Plugin', function() {

        cy.login(admin, adminPassword);
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
		cy.get("body").then($body => {
			if (!($body.find('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > :nth-child(3) > :nth-child(1) > :checked').length > 0)) {
				cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) >').click();
				cy.get('div:contains(\'The plugin "Dataverse Plugin" has been enabled.\')');
			}
		});
        cy.logout();
    });

    it('View Form', function() {

        cy.login(admin, adminPassword);
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
		cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras').click();
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)').click();
        cy.get('form[id="dataverseConfigurationForm"]');
        cy.logout();
    });

});