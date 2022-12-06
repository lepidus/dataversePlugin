var adminUser = Cypress.env('adminUser');
var adminPassword = Cypress.env('adminPassword');

function activeDataversePlugin() {
	cy.login(adminUser, adminPassword);
	cy.get('a:contains(' + adminUser + '):visible').click();
	cy.get('a:contains("Dashboard"):visible').click();
	cy.get('.app__nav a')
		.contains('Website')
		.click();
	cy.get('button[id="plugins-button"]').click();
	cy.get('body').then($body => {
		if (
			!(
				$body.find(
					'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > :nth-child(3) > :nth-child(1) > :checked'
				).length > 0
			)
		) {
			cy.get(
				'#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) >'
			).click();
			cy.get(
				'div:contains(\'The plugin "Dataverse Plugin" has been enabled.\')'
			);
		}
	});
	cy.get(
		'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras'
	).click();
	cy.get(
		'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)'
	).click();
	cy.get('input[name="dataverseUrl"]').invoke(
		'val',
		Cypress.env('dataverseURI')
	);
	cy.get('input[name="apiToken"]').invoke(
		'val',
		Cypress.env('dataverseAPIToken')
	);
	cy.get('input[name^="termsOfUse"]').invoke(
		'val',
		Cypress.env('dataverseTermsOfUse')
	);
	cy.get(
		'form[id="dataverseAuthForm"] button[name="submitFormButton"]'
	).click();
	cy.get("div:contains('Your changes have been saved.')");
}

describe('Check form operation', function() {
	it('Configuration Performed', function() {
		activeDataversePlugin();
		cy.get(
			'form[id="dataverseAuthForm"] button[name="submitFormButton"]'
		).click();
		cy.get("div:contains('Your changes have been saved.')");
		cy.logout();
	});

	it('Dataverse URL not filled', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a')
			.contains('Website')
			.click();
		cy.get('button[id="plugins-button"]').click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras'
		).click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)'
		).click();
		cy.get('input[name="dataverseUrl"]').invoke('val', '');
		cy.get('input[name="apiToken"]').invoke(
			'val',
			Cypress.env('dataverseAPIToken')
		);
		cy.get(
			'form[id="dataverseAuthForm"] button[name="submitFormButton"]'
		).click();
		cy.contains('This field is required.');
		cy.logout();
	});

	it('Invalid URL', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a')
			.contains('Website')
			.click();
		cy.get('button[id="plugins-button"]').click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras'
		).click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)'
		).click();
		cy.get('input[name="dataverseUrl"]').invoke('val', 'invalidURL');
		cy.get('input[name="apiToken"]').invoke(
			'val',
			Cypress.env('dataverseAPIToken')
		);
		cy.get(
			'form[id="dataverseAuthForm"] button[name="submitFormButton"]'
		).click();
		cy.contains('Please enter a valid URL.');
		cy.logout();
	});

	it('API Token not filled', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a')
			.contains('Website')
			.click();
		cy.get('button[id="plugins-button"]').click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras'
		).click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)'
		).click();
		cy.get('input[name="dataverseUrl"]').invoke(
			'val',
			Cypress.env('dataverseURI')
		);
		cy.get('input[name="apiToken"]').invoke('val', '');
		cy.get(
			'form[id="dataverseAuthForm"] button[name="submitFormButton"]'
		).click();
		cy.contains('This field is required.');
		cy.logout();
	});

	it('Invalid credentials', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.get('.app__nav a')
			.contains('Website')
			.click();
		cy.get('button[id="plugins-button"]').click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras'
		).click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)'
		).click();
		cy.get('input[name="dataverseUrl"]').invoke(
			'val',
			Cypress.env('dataverseURI')
		);
		cy.get('input[name="apiToken"]').invoke('val', 'invalidToken');
		cy.get(
			'form[id="dataverseAuthForm"] button[name="submitFormButton"]'
		).click();
		cy.contains("Can't connect to Dataverse");
		cy.logout();
	});
});
