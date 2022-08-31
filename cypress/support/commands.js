import '../../../../../lib/pkp/cypress/support/commands';

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
			cy.get('a:contains("Add galley")').click();
			cy.wait(2000); // Avoid occasional failure due to form init taking time
			cy.get('div.pkp_modal_panel').then($modalDiv => {
				cy.wait(3000);
				if ($modalDiv.find('div.header:contains("Create New Galley")').length) {
					cy.get('div.pkp_modal_panel input[id^="label-"]').type(
						file.galleyLabel,
						{
							delay: 0
						}
					);
					cy.get('div.pkp_modal_panel button:contains("Save")').click();
					cy.wait(2000); // Avoid occasional failure due to form init taking time
				}
			});
			cy.get('select[id=genreId]').select(file.genre);
			cy.fixture(file.file, 'base64').then(fileContent => {
				cy.get('input[type=file]').upload({
					fileContent,
					fileName: file.fileName,
					mimeType: 'application/pdf',
					encoding: 'base64'
				});
			});
			cy.get('button')
				.contains('Continue')
				.click();
			cy.wait(2000);

			cy.get('button')
				.contains('Continue')
				.click();
			cy.get('button')
				.contains('Complete')
				.click();
		});
		// Other applications use the submission files list panel
	}

	cy.get(
		'.pkp_controllers_grid > .header > .actions > #send_dataset_pkp_button > a'
	).click();
	cy.wait(2000);
	cy.get('.pkp_loading').contains('Loading');
	cy.get('input[id^="galleyItems"]').click({multiple: true});
	cy.get('input[id=publishData]').click();
	cy.get('button#saveDatasetButton').click();

	cy.get(
		'.pkp_controllers_grid > .header > .actions > #send_dataset_pkp_button > a'
	).click();
	cy.wait(2000);
	cy.get('[type="checkbox"]').should('be.checked');
	cy.get('button#saveDatasetButton').click();

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
	cy.get('input[id^="title-"').type(data.title, {delay: 0});
	cy.get('label')
		.contains('Title')
		.click(); // Close multilingual popover
	cy.get('textarea[id^="abstract-"').then(node => {
		cy.setTinyMceContent(node.attr('id'), data.abstract);
	});
	cy.get('ul[id^="keywords-"]').then(node => {
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
		cy.get('input[id^="givenName-"]').type(author.givenName, {
			delay: 0
		});
		cy.get('input[id^="familyName-"]').type(author.familyName, {
			delay: 0
		});
		cy.get('select[id=country]').select(author.country);
		cy.get('input[id^="email"]').type(author.email, {delay: 0});
		if ('affiliation' in author)
			cy.get('input[id^="affiliation-"]').type(author.affiliation, {
				delay: 0
			});
		cy.get('label')
			.contains(author.role)
			.click();
		cy.get('form#editAuthor')
			.find('button:contains("Save")')
			.click();
		cy.get(
			'div[id^="component-grid-users-author-authorgrid-"] span.label:contains("' +
				Cypress.$.escapeSelector(author.givenName + ' ' + author.familyName) +
				'")'
		);
	});
	cy.waitJQuery();
	cy.get('form[id=submitStep3Form]')
		.find('button')
		.contains('Save and continue')
		.click();

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
