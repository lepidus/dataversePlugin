
describe('Plugin configuration', function () {

	it('Check Configuration', function () {
		const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin';

		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('a:contains("Website")').click();

		cy.waitJQuery();
		cy.get('button#plugins-button').click();

		cy.get('input[id^=select-cell-dataverseplugin]').check();
		cy.get('input[id^=select-cell-dataverseplugin]').should('be.checked');

		cy.get('tr#' + pluginRowId + ' a.show_extras').click();
		cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

		cy.get('input[name=dataverseUrl]').focus().clear();
		cy.get('input[name=apiToken]').focus().clear();
		cy.get('input[name="termsOfUse[en_US]"]').focus().clear();

		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('label[for^=dataverseUrl].error').should('contain', 'This field is required.');
		cy.get('label[for^=apiToken].error').should('contain', 'This field is required.');
		cy.get('label[for^=termsOfUse].error').should('contain', 'This field is required.');

		cy.get('input[name=dataverseUrl]').focus().clear().type('dataverseUrl');
		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('label[for^=dataverseUrl].error').should('contain', 'Please enter a valid URL.');

		cy.get('input[name="termsOfUse[en_US]"]').focus().clear().type('invalidTermsOfUse');
		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('label[for^=termsOfUse].error').should('contain', 'Please enter a valid URL.');

		cy.get('input[name=dataverseUrl]').focus().clear().type(Cypress.env('dataverseUrl'));
		cy.get('input[name=apiToken]').focus().clear().type('invalidToken');
		cy.get('input[name="termsOfUse[en_US]"]').focus().clear().type(Cypress.env('termsOfUse'));

		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.contains("Can't connect to Dataverse");

		cy.get('input[name=apiToken]').focus().clear().type(Cypress.env('apiToken'));

		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('div:contains("Your changes have been saved.")');
	});
});
