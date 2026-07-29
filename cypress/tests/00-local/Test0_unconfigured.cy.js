describe('Plugin without configuration', function () {
	it('Does not break the submission view', function () {
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
});
