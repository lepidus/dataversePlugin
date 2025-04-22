Cypress.Commands.add('changeAuthorEditPermissionOnPublication', function(username, password, fullName, context, option) {
	var familyName = fullName.split(' ')[1];
    context = context || 'publicknowledge';
	cy.login(username, password, context);
	cy.findSubmissionAsEditor(username, password, familyName, context);
	cy.contains('span', fullName).parent().siblings('.show_extras').first().click();
	cy.get('.pkp_linkaction_icon_edit_user:visible').click();
	
	if (option == 'check') {
		cy.get('input[name="canChangeMetadata"]').check();
	} else {
		cy.get('input[name="canChangeMetadata"]').uncheck();
	}
	cy.get('[id^="submitFormButton"]').contains('OK').click();
	cy.contains('The stage assignment has been changed.');
	cy.logout();
});

Cypress.Commands.add('findSubmission', function(tab, title) {
	cy.get('#' + tab + '-button').click();
    cy.get('.listPanel__itemSubtitle:visible:contains("' + title + '")').first()
        .parent().parent().within(() => {
            cy.get('.pkpButton:contains("View")').click();
        });
});

Cypress.Commands.add('waitDataStatementTabLoading', function () {
	cy.intercept('GET', '**/api/v1/dataverse/dataverseName*').as('getDataverseNameRequest');
	cy.wait('@getDataverseNameRequest', {timeout:10000});
});

Cypress.Commands.add('waitDatasetTabLoading', function () {
	cy.wait(2500);
	cy.intercept('GET', /\/api\/v1\/datasets\/\d+\/citation/).as('getDatasetRequest');
	cy.wait('@getDatasetRequest', {timeout:10000});
});