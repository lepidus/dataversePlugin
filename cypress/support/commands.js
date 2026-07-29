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
	cy.findSubmission('active', submissionTitle);
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

Cypress.Commands.add('waitDataStatementTabLoading', function () {
	cy.intercept('GET', '**/api/v1/dataverse/dataverseName*').as('getDataverseNameRequest');
	cy.wait('@getDataverseNameRequest', {timeout:10000});
});

Cypress.Commands.add('waitDatasetTabLoading', function () {
	cy.intercept('GET', /\/api\/v1\/datasets\/\d+\/citation/).as('getDatasetRequest');
	cy.wait('@getDatasetRequest', {timeout:10000});
});

Cypress.Commands.add('advanceSubmissionSteps', function (numberOfSteps) {
	for (let step = 0; step < numberOfSteps; step++) {
		cy.location('hash').then((currentHash) => {
			cy.intercept('POST', /submissions\/\d+\/submit/).as('validateSubmissionStep');
			cy.contains('button', 'Continue').click();
			cy.location('hash').should('not.eq', currentHash).then((newHash) => {
				if (newHash === '#review') {
					cy.wait('@validateSubmissionStep');
				}
			});
		});
	}
});

Cypress.Commands.add('addKeyword', function (inputSelector, selectedSelector, keyword) {
	cy.intercept('GET', '**/api/v1/vocabs*').as('getKeywordSuggestions');
	cy.get(inputSelector).type(keyword, {delay: 0});
	cy.wait('@getKeywordSuggestions').its('response.statusCode').should('eq', 200);
	cy.get('body').then(($body) => {
		const matchingSuggestions = $body
			.find('[class*="autosuggest__results"] *')
			.filter((index, element) => element.textContent.trim() === keyword);

		if (matchingSuggestions.length) {
			cy.wrap(matchingSuggestions.first()).click();
		} else {
			cy.get(inputSelector).type('{enter}', {delay: 0});
		}
	});
	cy.get(selectedSelector).within(() => {
		cy.contains('.pkpAutosuggest__selection', keyword);
	});
});
