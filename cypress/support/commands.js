Cypress.Commands.add('findSubmission', function(tab, title) {
	// Workflow requests may finish as soon as View opens the page.
	cy.observeDataverseWorkflow();
	cy.get('#' + tab + '-button').click();
    cy.get('.listPanel__itemSubtitle:visible').filter((index, element) => element.textContent.includes(title))
        .should('have.length', 1)
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
	cy.wait('@getDataverseNameRequest', {timeout: 10000}).its('response.statusCode').should('eq', 200);
});

Cypress.Commands.add('waitDatasetTabLoading', function () {
	cy.wait('@getDatasetRequest', {timeout: 10000}).its('response.statusCode').should('eq', 200);
});

Cypress.Commands.add('addDataverseKeyword', function (selector, keyword) {
	cy.get(selector).type(keyword, {delay: 0});
	cy.get(selector).closest('.pkpAutosuggest')
		.contains('[role="option"]', keyword).should('be.visible');
	cy.get(selector).type('{enter}', {delay: 0});
	cy.get(selector).closest('.pkpAutosuggest')
		.contains('.pkpAutosuggest__selection', keyword).should('be.visible');
	cy.get(selector).should('have.value', '');
});

Cypress.Commands.add('observeDataverseWorkflow', function () {
	cy.intercept('GET', '**/api/v1/dataverse/dataverseName*').as('getDataverseNameRequest');
	cy.intercept('GET', /\/api\/v1\/datasets\/\d+\/citation(?:\?|$)/).as('getDatasetRequest');
});

Cypress.Commands.add('advanceSubmissionSteps', function (count = 1) {
	for (let step = 0; step < count; step++) {
		cy.get('.pkpSteps__step__label--current').invoke('text').then((currentStep) => {
			cy.contains('button', 'Continue').click();
			cy.get('.pkpSteps__step__label--current').should(($step) => {
				expect($step.text()).not.to.equal(currentStep);
			});
			cy.get('.submissionWizard__loadingReview').should('not.exist');
		});
	}
});

Cypress.Commands.add('uploadResearchDataFile', function (file, fileName, mimeType, encoding) {
	cy.contains('button', 'Add research data').click();
	cy.intercept('POST', '**/api/v1/temporaryFiles*').as('uploadResearchData');
	cy.fixture(file, encoding).then((fileContent) => {
		cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
			fileContent, fileName, mimeType, encoding,
		});
	});
	cy.wait('@uploadResearchData').its('response.statusCode').should('eq', 200);
	cy.get('[data-modal="addDatasetFileModal"] .pkpFormField--upload__fileName').should('contain', fileName);
	cy.saveResearchDataFile();
	cy.get('#datasetFiles').contains('a', fileName).should('be.visible');
});

Cypress.Commands.add('saveResearchDataFile', function () {
	cy.get('[data-modal="addDatasetFileModal"] input[name="termsOfUse"]').check();
	cy.get('[data-modal="addDatasetFileModal"] form').invoke('attr', 'action').then((action) => {
		cy.intercept('POST', action).as('saveResearchDataFile');
	});
	cy.contains('[data-modal="addDatasetFileModal"] button', 'Save').click();
	cy.wait('@saveResearchDataFile').its('response.statusCode').should('eq', 200);
	cy.get('[data-modal="addDatasetFileModal"]').should('not.exist');
});

Cypress.Commands.add('submitResearchData', function () {
	// Review validation and the final submission share the same endpoint.
	cy.intercept('POST', /\/submissions\/\d+\/submit(?:\?|$)/, (request) => {
		const isValidation = typeof request.body === 'string'
			? new URLSearchParams(request.body).has('_validateOnly')
			: request.body && request.body._validateOnly;
		if (!isValidation) {
			request.alias = 'submitResearchData';
		}
	});
	cy.get('.submissionWizard__loadingReview').should('not.exist');
	cy.contains('button', 'Submit').click();
	cy.get('.modal__panel:visible').within(() => {
		cy.contains('button', 'Submit').click();
	});
	cy.wait('@submitResearchData', {responseTimeout: 240000})
		.its('response.statusCode').should('eq', 200);
	cy.contains('h1', 'Submission complete').should('be.visible');
});

Cypress.Commands.add('saveDatasetMetadata', function () {
	cy.get('#datasetMetadata-datasetTitle-control').closest('form').invoke('attr', 'action').then((action) => {
		cy.intercept('POST', action).as('saveDatasetMetadata');
	});
	cy.get('#datasetMetadata-datasetTitle-control').closest('form').contains('button', 'Save').click();
	cy.wait('@saveDatasetMetadata', {responseTimeout: 240000})
		.then(({response}) => {
			expect(response.statusCode, response.body && response.body.error).to.equal(200);
		});
});

Cypress.Commands.add('confirmDatasetDeletion', function (label = 'Delete') {
	cy.intercept('POST', /\/api\/v1\/datasets\/\d+(?:\?|$)/).as('deleteResearchData');
	cy.get('.modal__panel:visible').contains('button', label).click();
	cy.wait('@deleteResearchData').its('response.statusCode').should('eq', 200);
	cy.contains('No research data transferred.').should('be.visible');
});

Cypress.Commands.add('configureDataverseForTests', function () {
	const pluginRow = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin';
	cy.loginDataverseUser('dbarnes');
	cy.contains('a', 'Website').click();
	cy.get('#plugins-button').click();
	cy.setDataversePluginEnabled(true);
	cy.get(`#${pluginRow} a.show_extras`).click();
	cy.get(`a[id^=${pluginRow}-settings-button]`).click();
	cy.get('input[name="dataverseUrl"]').clear().type(Cypress.env('dataverseUrl'), {delay: 0});
	cy.get('input[name="apiToken"]').clear().type(Cypress.env('dataverseApiToken'), {delay: 0, log: false});
	cy.get('input[name="termsOfUse[en]"]').clear().type(Cypress.env('dataverseTermsOfUse'), {delay: 0});
	if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
		cy.get('input[name="datasetPublish"][value=2]').check();
	}
	cy.contains('#dataverseConfigurationForm button', 'OK').click();
	cy.contains('Your changes have been saved.').should('be.visible');
	cy.get('#dataverseConfigurationForm').should('not.exist');
});

Cypress.Commands.add('setDataversePluginEnabled', function (enabled) {
	const checkbox = 'input[id^=select-cell-dataverseplugin]';
	cy.get(checkbox).then(($checkbox) => {
		if ($checkbox.is(':checked') === enabled) {
			return;
		}
		const operation = enabled ? 'enable' : 'disable';
		cy.intercept('POST', `**/settings-plugin-grid/${operation}*`).as('toggleDataversePlugin');
		if (enabled) {
			cy.wrap($checkbox).check();
		} else {
			cy.wrap($checkbox).uncheck();
			cy.get('.pkp_modal_panel:visible').within(() => {
				cy.contains('Are you sure you want to disable this plugin?');
				cy.contains('button', 'OK').click();
			});
		}
		cy.wait('@toggleDataversePlugin').its('response.statusCode').should('eq', 200);
	});
	cy.get(checkbox).should(enabled ? 'be.checked' : 'not.be.checked');
});

Cypress.Commands.add('loginDataverseUser', function (username, context = 'publicknowledge') {
	cy.session(['dataverse', username, context], () => {
		cy.login(username, null, context);
		cy.get('.app__userNav').should('be.visible');
	}, {
		validate() {
			cy.request({
				url: `/index.php/${context}/user/profile`,
				followRedirect: false,
				log: false,
			}).its('status').should('eq', 200);
		},
	});
	cy.visit(`/index.php/${context}/submissions`);
});
