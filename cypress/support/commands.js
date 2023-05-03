Cypress.Commands.add('allowAuthorToEditPublication', function(username, password, fullName, context) {
	var familyName = fullName.split(' ')[1];
    context = context || 'publicknowledge';
	cy.login(username, password, context);
	cy.findSubmissionAsEditor(username, password, familyName, context);
	cy.get('#stageParticipantGridContainer- .label').contains(fullName)
		.parent().parent().find('.show_extras').click()
		.parent().parent().siblings().find('a').contains('Edit').click();
	cy.get('[name="canChangeMetadata"]').check();
	cy.get('[id^="submitFormButton"]').contains('OK').click();
	cy.contains('The stage assignment has been changed.');
	cy.wait(1000);
	cy.logout();
	cy.wait(1000);
});
