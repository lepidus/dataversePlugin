Cypress.Commands.add('findSubmission', function(tab, title) {
	cy.get('#' + tab + '-button').click();
    cy.get('.listPanel__itemSubtitle:visible:contains("' + title + '")').first()
        .parent().parent().within(() => {
            cy.get('.pkpButton:contains("View")').click();
        });
});

Cypress.Commands.add('changeAuthorEditPermissionOnPublication', function(username, fullName, context, submissionTitle, option) {
	var familyName = fullName.split(' ')[1];
    context = context || 'publicknowledge';
	cy.login(username, null, context);
	cy.log('Login como dbarnes feito');
	cy.findSubmission('active', submissionTitle);
	cy.log('Submissão encontrada');
	cy.contains('span', fullName).parent().siblings('.show_extras').first().click();
	cy.get('.pkp_linkaction_icon_edit_user:visible').click();
	
	if (option == 'check') {
		cy.get('input[name="canChangeMetadata"]').check();
	} else {
		cy.get('input[name="canChangeMetadata"]').uncheck();
	}
	cy.log('Permissões de Elinor Ostrom alteradas');
	cy.get('[id^="submitFormButton"]').contains('OK').click();
	cy.contains('The stage assignment has been changed.');
	cy.log('Confirmado que salvou');
	cy.logout();
});

Cypress.Commands.add('waitDataStatementTabLoading', function () {
	cy.intercept('GET', '**/api/v1/dataverse/dataverseName*').as('getDataverseNameRequest');
	cy.wait('@getDataverseNameRequest', {timeout:10000});
});

Cypress.Commands.add('waitDatasetTabLoading', function () {
	cy.intercept('GET', /\/api\/v1\/datasets\/\d+\/citation/).as('getDatasetRequest');
	cy.wait('@getDatasetRequest', {timeout:10000});
	cy.wait(1000);
});