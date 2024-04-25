describe('Plugin configuration', function () {
	it('Check plugin does not break submission view before configuration', function () {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();

		cy.get('input[id^=select-cell-dataverseplugin]').check();
		cy.get('input[id^=select-cell-dataverseplugin]').should('be.checked');

		cy.contains('a', 'Submissions').click();
		cy.get('#active-button').click();
		cy.get('.pkpButton:visible:contains("View")').first().click();
		cy.get('#publication-button').click();
	});
	it('Configures plugin', function() {
		const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin';
		
		cy.login('dbarnes', null, 'publicknowledge');
		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();

		cy.get('tr#' + pluginRowId + ' a.show_extras').click();
		cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

		cy.get('input[name=dataverseUrl]').focus().clear();
		cy.get('input[name=apiToken]').focus().clear();
		cy.get('input[name="termsOfUse[en]"]').focus().clear();

		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('label[for^=dataverseUrl].error').should('contain', 'This field is required.');
		cy.get('label[for^=apiToken].error').should('contain', 'This field is required.');
		cy.get('label[for^=termsOfUse].error').should('contain', 'This field is required.');

		cy.get('input[name=dataverseUrl]').focus().clear().type('dataverseUrl');
		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('label[for^=dataverseUrl].error').should('contain', 'Please enter a valid URL.');

		cy.get('input[name="termsOfUse[en]"]').focus().clear().type('invalidTermsOfUse');
		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('label[for^=termsOfUse].error').should('contain', 'Please enter a valid URL.');

		cy.get('input[name=dataverseUrl]').focus().clear().type(Cypress.env('dataverseUrl'));
		cy.get('input[name=apiToken]').focus().clear().type('invalidToken');
		cy.get('input[name="termsOfUse[en]"]').focus().clear().type(Cypress.env('dataverseTermsOfUse'));

		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.contains("Can't connect to Dataverse");

		cy.get('input[name=apiToken]').focus().clear().type(Cypress.env('dataverseApiToken'));

		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.contains("It is required to select the event to publish the research data.");
			cy.get('input[name="datasetPublish"][value=2]').check();
		}

		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('div:contains("Your changes have been saved.")');
	});
});
