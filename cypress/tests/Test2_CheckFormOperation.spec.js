var adminUser = Cypress.env('adminUser');
var adminPassword = Cypress.env('adminPassword');

describe('Check form operation', function() {
	it('Configuration Performed', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a').contains('Website').click();
		cy.get('button[id="plugins-button"]').click();
		cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin').then($pluginRow => {
			if (!$pluginRow.find('input[id^="select-cell-dataverseplugin-enable"]:checked').length) {
				cy.get('input[id^="select-cell-dataverseplugin-enable"]').check();
				cy.get('div').contains('The plugin "Dataverse Plugin" has been enabled');
			}
			cy.wrap($pluginRow).contains('Settings').click();
			cy.get('a[id*="dataverseplugin-settings-button"]').click();
			cy.get('input[name="dataverseUrl"]').invoke('val', Cypress.env('dataverseURI'));
			cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
			cy.get('input[name^="termsOfUse"]').first().invoke('val', Cypress.env('dataverseTermsOfUse'));
			cy.get('form[id="dataverseConfigurationForm"] button[name="submitFormButton"]').click();
			cy.get("div:contains('Your changes have been saved.')");
		});
	});

	it('Dataverse URL not filled', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a').contains('Website').click();
		cy.get('button[id="plugins-button"]').click();
		cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin').then($pluginRow => {
			if (!$pluginRow.find('input[id^="select-cell-dataverseplugin-enable"]:checked').length) {
				cy.get('input[id^="select-cell-dataverseplugin-enable"]').check();
				cy.get('div').contains('The plugin "Dataverse Plugin" has been enabled');
			}
			cy.wrap($pluginRow).contains('Settings').click();
			cy.get('a[id*="dataverseplugin-settings-button"]').click();
			cy.get('input[name="dataverseUrl"]').invoke('val', '');
			cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
			cy.get('input[name^="termsOfUse"]').first().invoke('val', Cypress.env('dataverseTermsOfUse'));
			cy.get('form[id="dataverseConfigurationForm"] button[name="submitFormButton"]').click();
			cy.contains('This field is required.');
		});		
		cy.logout();
	});

	it('Invalid URL', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a').contains('Website').click();
		cy.get('button[id="plugins-button"]').click();
		cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin').then($pluginRow => {
			if (!$pluginRow.find('input[id^="select-cell-dataverseplugin-enable"]:checked').length) {
				cy.get('input[id^="select-cell-dataverseplugin-enable"]').check();
				cy.get('div').contains('The plugin "Dataverse Plugin" has been enabled');
			}
			cy.wrap($pluginRow).contains('Settings').click();
			cy.get('a[id*="dataverseplugin-settings-button"]').click();
			cy.get('input[name="dataverseUrl"]').invoke('val', 'invalidURL');
			cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
			cy.get('input[name^="termsOfUse"]').first().invoke('val', Cypress.env('dataverseTermsOfUse'));
			cy.get('form[id="dataverseConfigurationForm"] button[name="submitFormButton"]').click();
			cy.contains('Please enter a valid URL.');
		});		
		cy.logout();
	});

	it('API Token not filled', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a').contains('Website').click();
		cy.get('button[id="plugins-button"]').click();
		cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin').then($pluginRow => {
			if (!$pluginRow.find('input[id^="select-cell-dataverseplugin-enable"]:checked').length) {
				cy.get('input[id^="select-cell-dataverseplugin-enable"]').check();
				cy.get('div').contains('The plugin "Dataverse Plugin" has been enabled');
			}
			cy.wrap($pluginRow).contains('Settings').click();
			cy.get('a[id*="dataverseplugin-settings-button"]').click();
			cy.get('input[name="dataverseUrl"]').invoke('val', Cypress.env('dataverseURI'));
			cy.get('input[name="apiToken"]').invoke('val', '');
			cy.get('input[name^="termsOfUse"]').first().invoke('val', Cypress.env('dataverseTermsOfUse'));
			cy.get('form[id="dataverseConfigurationForm"] button[name="submitFormButton"]').click();
			cy.contains('This field is required.');
		});		
		cy.logout();
	});

	it('Terms of Use not filled', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a').contains('Website').click();
		cy.get('button[id="plugins-button"]').click();
		cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin').then($pluginRow => {
			if (!$pluginRow.find('input[id^="select-cell-dataverseplugin-enable"]:checked').length) {
				cy.get('input[id^="select-cell-dataverseplugin-enable"]').check();
				cy.get('div').contains('The plugin "Dataverse Plugin" has been enabled');
			}
			cy.wrap($pluginRow).contains('Settings').click();
			cy.get('a[id*="dataverseplugin-settings-button"]').click();
			cy.get('input[name="dataverseUrl"]').invoke('val', Cypress.env('dataverseURI'));
			cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
			cy.get('input[name^="termsOfUse"]').first().invoke('val', '');
			cy.get('form[id="dataverseConfigurationForm"] button[name="submitFormButton"]').click();
			cy.contains('This field is required.');
		});		
		cy.logout();
	});

	it('Invalid credentials', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a').contains('Website').click();
		cy.get('button[id="plugins-button"]').click();
		cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin').then($pluginRow => {
			if (!$pluginRow.find('input[id^="select-cell-dataverseplugin-enable"]:checked').length) {
				cy.get('input[id^="select-cell-dataverseplugin-enable"]').check();
				cy.get('div').contains('The plugin "Dataverse Plugin" has been enabled');
			}
			cy.wrap($pluginRow).contains('Settings').click();
			cy.get('a[id*="dataverseplugin-settings-button"]').click();
			cy.get('input[name="dataverseUrl"]').invoke('val', Cypress.env('dataverseURI'));
			cy.get('input[name="apiToken"]').invoke('val', 'invalidToken');
			cy.get('input[name^="termsOfUse"]').first().invoke('val', Cypress.env('dataverseTermsOfUse'));
			cy.get('form[id="dataverseConfigurationForm"] button[name="submitFormButton"]').click();
			cy.contains("Can't connect to Dataverse");
		});		
		cy.logout();
	});
});
