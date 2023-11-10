Cypress.Commands.add('allowAuthorToEditPublication', function(username, password, fullName, context) {
	var familyName = fullName.split(' ')[1];
    context = context || 'publicknowledge';
	cy.login(username, password, context);
	cy.findSubmissionAsEditor(username, password, familyName, context);
	cy.contains('span', fullName).parent().siblings('.show_extras').first().click();
	cy.get('.pkp_linkaction_icon_edit_user:visible').click();
	cy.get('[name="canChangeMetadata"]').check();
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