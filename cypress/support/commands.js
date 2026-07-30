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

let keywordSuggestionRequest = 0;

Cypress.Commands.add('addKeyword', function (inputSelector, selectedSelector, keyword) {
	const requestAlias = `getKeywordSuggestions${++keywordSuggestionRequest}`;
	cy.intercept('GET', '**/api/v1/vocabs*').as(requestAlias);
	cy.get(inputSelector).type(keyword, {delay: 0});
	cy.wait(`@${requestAlias}`).then((interception) => {
		expect(interception.response.statusCode).to.eq(200);
		cy.contains('.autosuggest__results-item', keyword).click();
	});
	cy.get(inputSelector).should('have.value', '');
	cy.get(selectedSelector).within(() => {
		cy.contains('.pkpAutosuggest__selection', keyword);
	});
});

Cypress.Commands.add('configureDataverse', function (configuration) {
	cy.getCsrfToken();
	cy.get('@csrfToken').then((csrfToken) => {
		cy.request({
			url: '/index.php/publicknowledge/$$$call$$$/grid/settings/plugins/settings-plugin-grid/manage'
				+ '?verb=settings&plugin=dataverseplugin&category=generic&save=1',
			method: 'POST',
			form: true,
			body: {
				csrfToken,
				dataverseUrl: configuration.url,
				apiToken: configuration.apiToken,
				'termsOfUse[en]': configuration.termsOfUse,
				'additionalInstructions[en]': '',
				datasetPublish: configuration.datasetPublish,
			},
		}).then((response) => {
			expect(response.status).to.eq(200);
			expect(response.body.status).to.eq(true);
		});
	});
});
