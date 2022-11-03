import '../../../../../lib/pkp/cypress/support/commands';

Cypress.on('uncaught:exception', (err, runnable) => {
	// returning false here prevents Cypress from failing the test
	return false
})

Cypress.Commands.add('DataverseCreateSubmission', (data, context) => {
	// Initialize some data defaults before starting
	if (!('files' in data))
		data.files = [
			{
				file: 'dummy.pdf',
				fileName: data.title + '.pdf',
				fileTitle: data.title,
				genre: Cypress.env('defaultGenre'),
				publishData: false
			}
		];
	else
		data.files.forEach(file => {
			if (!('publishData' in file)) file.publishData = false;
			if (!('galleyLabel' in file)) file.galleyLabel = 'PDF';
		});

	if (!('keywords' in data)) data.keywords = [];
	if (!('additionalAuthors' in data)) data.additionalAuthors = [];
	if ('series' in data) data.section = data.series; // OMP compatible
	// If 'additionalFiles' is specified, it's to be used to augment the default
	// set, rather than overriding it (as using 'files' would do). Add the arrays.
	cy.get(
		'a:contains("Make a New Submission"), div#myQueue a:contains("New Submission")'
	).click();

	// === Submission Step 1 ===
	if ('section' in data)
		cy.get('select[id="sectionId"],select[id="seriesId"]').select(data.section);
	cy.get('input[id^="checklist-"]').click({multiple: true});
	switch (
		data.type // Only relevant to OMP
	) {
		case 'monograph':
			cy.get('input[id="isEditedVolume-0"]').click();
			break;
		case 'editedVolume':
			cy.get('input[id="isEditedVolume-1"]').click();
			break;
	}
	cy.get('input[id=privacyConsent]').click();
	if ('submitterRole' in data) {
		cy.get('input[name=userGroupId]')
			.parent()
			.contains(data.submitterRole)
			.click();
	} else cy.get('input[id=userGroupId]').click();
	cy.get('button.submitFormButton').click();

	// === Submission Step 2 ===

	// OPS uses the galley grid
	if (
		Cypress.env('contextTitles').en_US == 'Public Knowledge Preprint Server'
	) {
		data.files.forEach(file => {
			cy.get('button:contains("Upload research data")').click();
			cy.get('[data-modal="datasetModal"]').then($datasetModal => {
				cy.fixture(file.file, 'base64').then(fileContent => {
					cy.get('input[type=file]').upload({
						fileContent,
						fileName: file.fileName,
						mimeType: 'application/pdf',
						encoding: 'base64'
					});
				});
				cy.get('input[name="termsOfUse"').check();
				cy.get('[data-modal="datasetModal"] button:contains("Save")').click();
			});
			
		});
		// Other applications use the submission files list panel
	}

	// Save the ID to the data object
	cy.location('search').then(search => {
		// this.submission.id = parseInt(search.split('=')[1], 10);
		data.id = parseInt(search.split('=')[1], 10);
	});

	cy.get('button')
		.contains('Save and continue')
		.click();

	// === Submission Step 3 ===
	// Metadata fields
	cy.get('input[id^="title-en_US-"').type(data.title, {delay: 0});
	cy.get('label')
		.contains('Title')
		.click(); // Close multilingual popover
	cy.get('textarea[id^="abstract-en_US-"').then(node => {
		cy.setTinyMceContent(node.attr('id'), data.abstract);
	});
	cy.get('ul[id^="en_US-keywords-"]').then(node => {
		data.keywords.forEach(keyword => {
			node.tagit('createTag', keyword);
		});
	});

	cy.get('#authorsGridContainer .first_column > .show_extras').click();
	cy.get('#authorsGridContainer td a:contains("Delete")').click();
	cy.wait(250);
	cy.get('button')
		.contains('OK')
		.click();

	data.additionalAuthors.forEach(author => {
		if (!('role' in author)) author.role = 'Author';
		cy.get(
			'a[id^="component-grid-users-author-authorgrid-addAuthor-button-"]'
		).click();
		cy.wait(250);
		cy.get('input[id^="givenName-en_US-"]').type(author.givenName, {
			delay: 0
		});
		cy.get('input[id^="familyName-en_US-"]').type(author.familyName, {
			delay: 0
		});
		cy.get('select[id=country]').select(author.country);
		cy.get('input[id^="email"]').type(author.email, {delay: 0});
		if ('affiliation' in author)
			cy.get('input[id^="affiliation-en_US-"]').type(author.affiliation, {
				delay: 0
			});
		cy.get('label')
			.contains(author.role)
			.click();
		cy.get('form#editAuthor')
			.find('button:contains("Save")')
			.click();
		cy.wait(250);
		cy.get(
			'div[id^="component-grid-users-author-authorgrid-"] span.label:contains("' +
				Cypress.$.escapeSelector(author.givenName + ' ' + author.familyName) +
				'")'
		);
	});
	cy.waitJQuery();
	cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

	// === Submission Step 4 ===
	cy.wait(3000);
	cy.get('form[id=submitStep4Form]')
		.find('button')
		.contains('Finish Submission')
		.click();
	cy.get('button.pkpModalConfirmButton').click();
	cy.waitJQuery();
	cy.get('h2:contains("Submission complete")');
});
