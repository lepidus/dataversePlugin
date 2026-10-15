import '../support/commands.js';

describe('Dataverse Plugin - Custom required metadata fields', function () {

    let submission;

    before(function() {
        if (!Cypress.env('dataverseCustomRequiredMetadataFieldsUrl')) {
            this.skip();
        }
		submission = {
			title: 'The Impact of Quantum Fluctuations on Urban Bee Populations: A Multidisciplinary Approach',
            abstract: 'This groundbreaking study explores the intersection of quantum physics and urban ecology to understand the effects of quantum fluctuations on bee populations in metropolitan areas.',
		}
	});

    it('Configures plugin to Dataverse with custom required metadata fields', function() {
		const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin';
		
		cy.login('dbarnes', null, 'publicknowledge');
		cy.contains('a', 'Website').click();

		cy.get('#plugins-button').click();

		cy.get('tr#' + pluginRowId + ' a.show_extras').click();
		cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

		cy.get('input[name=dataverseUrl]').focus().clear().type(Cypress.env('dataverseCustomRequiredMetadataFieldsUrl'), {delay: 0});
        cy.get('input[name=apiToken]').focus().clear().type(Cypress.env('dataverseApiToken'), {delay: 0, log: false});
		cy.get('input[name="termsOfUse[en]"]').focus().clear().type(Cypress.env('dataverseTermsOfUse'), {delay: 0});

		if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
			cy.get('input[name="datasetPublish"][value=2]').check();
		}

		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('div:contains("Your changes have been saved.")');
	});

    it('Show custom required metadata fields in research data form', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.get('div#myQueue a:contains("New Submission")').click();

        cy.get('input[name="locale"][value="en"]').click();
        cy.setTinyMceContent('startSubmission-title-control', submission.title);
        if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
            cy.get('input[name="sectionId"][value="1"]').click();
        }
        cy.get('input[name="submissionRequirements"]').check();
        cy.get('input[name="privacyConsent"]').check();
        cy.contains('button', 'Begin Submission').click();

        // Step 1
        cy.setTinyMceContent('titleAbstract-abstract-control-en', submission.abstract);
        cy.get('input[name="dataStatementTypes"][value=3]').click();
        cy.advanceSubmissionSteps();

        // Step 2
        cy.get('.pkpSteps__step__label:contains("Upload Files")').click();
        cy.uploadSubmissionFiles([{
			'file': 'dummy.pdf',
			'fileName': 'dummy.pdf',
			'mimeType': 'application/pdf',
			'genre': 'Article Text'
		}]);
        cy.uploadResearchDataFile('example.json', 'dataset.json', 'application/json', 'utf8');
        cy.uploadResearchDataFile('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'README.pdf', 'application/pdf', 'base64');
        cy.advanceSubmissionSteps();

        // Step 3
        cy.advanceSubmissionSteps();

        // Step 4
        cy.contains('Alternative URL');
        cy.contains('Description Date');
        cy.contains('Are the original data publicly available?');
        cy.contains('Is the original code available?');

        cy.get('button').contains('Save for Later').click();
        cy.contains('Saved for Later');
        cy.logout();
    });

    it('Can not submit without filling custom required metadata fields', function () {
        const metadataFields = [
            'Alternative URL',
            'Description Date',
            'Are the original data publicly available?',
            'Is the original code available?'
        ];

        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submission.title);

        cy.advanceSubmissionSteps();

        cy.get('button:contains("Submit")').should('be.disabled');
        cy.get('h3:contains("Research data metadata")')
            .then($h3 => {
                metadataFields.forEach(field => {
                    cy.wrap($h3)
                        .parents('.submissionWizard__reviewPanel')
                        .find('h4:contains("' + field + '")')
                        .parent()
                        .contains('This field is required.');
                });
            });

        cy.logout();
    });

    it('Can not submit with invalid metadata fields values', function () {
        const metadataFields = {
            'Alternative URL': 'This is not a valid URL',
            'Description Date': 'This is not a valid date',
        }

        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submission.title);

        cy.get('#datasetMetadata-datasetAlternativeURL-control').type('invalid-url', {delay: 0});
        cy.get('#datasetMetadata-datasetDsDescriptionDate-control').type('june 32, 2023', {delay: 0});

        cy.advanceSubmissionSteps();

        cy.get('button:contains("Submit")').should('be.disabled');
        cy.get('h3:contains("Research data metadata")')
            .then($h3 => {
                Object.keys(metadataFields).forEach((field) => {
                    cy.wrap($h3)
                        .parents('.submissionWizard__reviewPanel')
                        .find('h4:contains("' + field + '")')
                        .parent()
                        .contains(metadataFields[field]);
                });
            });

        cy.logout();
    });

    it('Submit with required metadata fields values', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submission.title);

        cy.get('select[name="datasetLanguage"]').select('English');
        cy.get('select[name="datasetSubject"]').select('Earth and Environmental Sciences');
        cy.get('select[name="datasetLicense"]').select('CC BY 4.0');
        cy.get('select[name="datasetRelationType"]').select('Cites');
        cy.get('#datasetMetadata-datasetAlternativeURL-control').focus().clear().type('https://example.com', {delay: 0});
        cy.get('#datasetMetadata-datasetDsDescriptionDate-control').focus().clear().type('2023-06-01', {delay: 0});
        cy.get('select[name="datasetPSRI1"]').select('Yes');
        cy.get('select[name="datasetPSRI2"]').select('Yes');
        
        cy.advanceSubmissionSteps();

        cy.submitResearchData();
    });

    it('Submit dataset with custom required metadata fields in workflow page', function () {
        if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
            cy.changeAuthorEditPermissionOnPublication('dbarnes', 'Elinor Ostrom', 'publicknowledge', submission.title, 'check');
		}

        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submission.title);
        
        cy.waitDatasetTabLoading();

        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();

        cy.get('#deleteDatasetButton').click();
        cy.contains('Are you sure you want to permanently delete the research data related to this submission?');
		cy.confirmDatasetDeletion('Delete');

        cy.contains('button', 'Upload research data').click();
        cy.uploadResearchDataFile('example.json', 'example.json', 'application/json', 'utf8');
    
        cy.uploadResearchDataFile('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'README.pdf', 'application/pdf', 'base64');

        cy.get('select[name="datasetLanguage"]').select('English');
        cy.get('select[name="datasetSubject"]').select('Earth and Environmental Sciences');
        cy.get('select[name="datasetLicense"]').select('CC BY 4.0');
        cy.get('select[name="datasetRelationType"]').select('Cites');
        cy.get('#datasetMetadata-datasetAlternativeURL-control').focus().clear().type('https://example.com', {delay: 0});
        cy.get('#datasetMetadata-datasetDsDescriptionDate-control').focus().clear().type('2023-06-01', {delay: 0});
        cy.get('select[name="datasetRelationType"]').select('Cites');
        cy.get('select[name="datasetPSRI1"]').select('Yes');
        cy.get('select[name="datasetPSRI2"]').select('Yes');
        cy.get('button:visible:contains("Save")').click();

        cy.contains('h1', 'Research data', {timeout:10000});
    });
});