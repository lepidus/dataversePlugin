import '../support/commands.js';

function assertAdditionalInstructionsDisplay() {
	cy.contains('1. Submit under "Research Data" any files that have been collected');
	cy.contains('2. It is mandatory to include a file named "Readme"/"Leiame"/"Leame"');
	cy.contains('For additional guidance on creating the file, consult the suggested references below');
	cy.contains('3. The files deposited in "Research Data" will form a dataset');
}

describe('Dataverse Plugin - Workflow features', function () {
	let submissionData;
    
    before(function () {
		submissionData = {
			title: 'Workflow features with controlled research data',
			abstract: 'Mass public transportation can be used as a way to reduce greenhouse gases emissions.',
			keywords: [
                'mass public transport',
			],
			dataStatementTypes: [2, 3, 5],
			dataStatementUrls: [
				'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM',
			],
			dataStatementReason: 'Has sensitive data',
			articleFileName: 'workflow-article.pdf',
			datasetLanguage: 'French',
			datasetSubject: 'Earth and Environmental Sciences',
			datasetLicense: 'CC BY 4.0',
			datasetRelationType: 'IsSupplementedBy',
		};

		cy.startControlledDataverse('doi:10.5072/FK2/WORKFLOWAUTHOR').then((controlledDataverseUrl) => {
			cy.login('dbarnes', null, 'publicknowledge');
			cy.ensureDataversePluginEnabled();
			cy.configureDataverse({
				url: controlledDataverseUrl,
				apiToken: 'valid-token',
				termsOfUse: 'https://example.test/terms',
				datasetPublish: 2,
			});
			cy.logout();
		});

		cy.depositDataverseSubmissionWithApi('eostrom', submissionData, [{
			fixture: 'example.json',
			fileName: 'Planilha_de_dados_ÇÕÔÁÀÃ.json',
			mimeType: 'application/json',
			encoding: 'utf8',
		}, {
			fixture: '../../plugins/generic/dataverse/cypress/fixtures/README.pdf',
			fileName: 'LEIAME.pdf',
			mimeType: 'application/pdf',
			encoding: 'base64',
		}]);
	});

	after(function () {
		const externalDataverseConfiguration = {
			url: Cypress.env('dataverseUrl'),
			apiToken: Cypress.env('dataverseApiToken'),
			termsOfUse: Cypress.env('dataverseTermsOfUse'),
		};
		const hasExternalDataverseConfiguration = Object.values(externalDataverseConfiguration)
			.every((value) => typeof value === 'string' && value.length > 0);

		if (hasExternalDataverseConfiguration) {
			cy.login('dbarnes', null, 'publicknowledge');
			cy.configureDataverse({
				...externalDataverseConfiguration,
				datasetPublish: 2,
			});
			cy.logout();
		}
		cy.stopControlledDataverse();
	});

    it('Data statement features are displayed in workflow tab', function () {
        if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
			cy.changeAuthorEditPermissionOnPublication('dbarnes', 'Elinor Ostrom', 'publicknowledge', submissionData.title, 'check');
		}
        
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('#publication-button').click();
        cy.contains('button', 'Data statement').click();

        cy.get('input[name="dataStatementTypes"][value=2]').should('be.checked');
        cy.get('input[name="dataStatementTypes"][value=5]').should('be.checked');
        cy.get('#dataStatement-dataStatementUrls-selected').should('be.visible');
        cy.get('#dataStatement-dataStatementUrls-selected').within(() => {
            cy.contains('a', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
        });
        cy.get('#dataStatement-dataStatementReason-control-en').should('be.visible');
        cy.get('#dataStatement-dataStatementReason-control-en').should('have.value', 'Has sensitive data');
        cy.get('input[name="researchDataSubmitted"]').should('be.checked');
        cy.get('input[name="researchDataSubmitted"]').should('be.disabled');

        cy.get('input[name="dataStatementTypes"][value=2]').uncheck();
        cy.get('#dataStatement-dataStatementUrls-selected').should('not.be.visible');
        cy.get('input[name="dataStatementTypes"][value=5]').uncheck();
        cy.get('#dataStatement-dataStatementReason-control-en').should('not.be.visible');
        cy.get('input[name="dataStatementTypes"][value=2]').check();
        cy.get('input[name="dataStatementTypes"][value=5]').check();

        cy.get('input[name="dataStatementTypes"][value=1]').check();
        cy.get('button:visible:contains("Save")').click();
        cy.get('.pkpFormPage__status:contains("Saved")');
        cy.reload();
        
        cy.get('input[name="dataStatementTypes"][value=1]').should('be.checked');
        cy.get('input[name="dataStatementTypes"][value=1]').uncheck();
        cy.get('button:visible:contains("Save")').click();
        cy.get('.pkpFormPage__status:contains("Saved")');
    });
    it('Research data metadata editing in workflow', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();

		cy.contains('.datasetLabel', 'Draft');
		cy.contains('.datasetLabel', 'Unpublished');

        cy.get('#datasetMetadata-datasetTitle-control').should('have.value', 'Replication data for: ' + submissionData.title);
        cy.getTinyMceContent('datasetMetadata-datasetDescription-control').should('include', submissionData.abstract);
        cy.get('#datasetMetadata-datasetKeywords-selected-en').within(() => {
            cy.contains(submissionData.keywords[0]);
        });
        cy.get('#datasetMetadata-datasetLanguage-control').should('have.value', 'French');
        cy.get('#datasetMetadata-datasetSubject-control').should('have.value', 'Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').should('have.value', 'CC BY 4.0');
        cy.get('#datasetMetadata-datasetRelationType-control').should('have.value', 'IsSupplementedBy');

        cy.get('#datasetMetadata-datasetTitle-control').clear().type('Test metadata editing', {delay: 0});
        cy.setTinyMceContent('datasetMetadata-datasetDescription-control', 'new description');
        cy.get('#datasetMetadata-datasetLanguage-control').select('English');
        cy.get('#datasetMetadata-datasetSubject-control').select('Computer and Information Science');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC0 1.0');
        cy.get('#datasetMetadata-datasetRelationType-control').select('Is Cited By');
        cy.get('button:visible:contains("Save")').click();
        cy.get('.pkpFormPage__status:contains("Saved")');

        cy.get('#datasetMetadata-datasetTitle-control').should('have.value', 'Test metadata editing');
        cy.getTinyMceContent('datasetMetadata-datasetDescription-control').should('include', 'new description');
        cy.get('#datasetMetadata-datasetLanguage-control').should('have.value', 'English');
        cy.get('#datasetMetadata-datasetSubject-control').should('have.value', 'Computer and Information Science');
        cy.get('#datasetMetadata-datasetLicense-control').should('have.value', 'CC0 1.0');
        cy.get('#datasetMetadata-datasetRelationType-control').should('have.value', 'IsCitedBy');

        cy.get('#datasetMetadata-datasetTitle-control').clear().type('Replication data for: ' + submissionData.title, {delay: 0});
        cy.getTinyMceContent('datasetMetadata-datasetDescription-control', submissionData.abstract);
        cy.get('#datasetMetadata-datasetSubject-control').select('Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
        cy.get('button:visible:contains("Save")').click();
        cy.get('.pkpFormPage__status:contains("Saved")');
    });
    it('Research data files editing in workflow', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.get('#publication-button').click();
        cy.intercept({
            method: 'GET',
            url: '**/api/v1/datasets/*/files*'
        }).as('loadDatasetFiles');
        cy.get('#datasetTab-button').click();
        cy.get('#dataset_files-button').click();
		cy.wait('@loadDatasetFiles').its('response.statusCode').should('eq', 200);

		cy.get('#datasetFiles').contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.json');
        cy.get('#datasetFiles').contains('a', 'LEIAME.pdf');
        cy.get('#datasetTab-button .pkpBadge').contains('2');

        cy.contains('button', 'Add research data').click();
        cy.fixture('example.json', 'utf8').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'example.json',
				mimeType: 'application/json',
				encoding: 'utf8',
			});
		});
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
		cy.wait('@loadDatasetFiles').its('response.statusCode').should('eq', 200);

        cy.get('#datasetFiles').contains('example.json');
        cy.get('#datasetTab-button .pkpBadge').contains('3');

        cy.get('.listPanel__item:contains("example.json") button:contains("Delete")').click();
		cy.get('.modal__panel--dialog button:contains("Delete File")').click();
		cy.wait('@loadDatasetFiles').its('response.statusCode').should('eq', 200);

        cy.get('#datasetFiles').should('not.include.text', 'example.json');
        cy.get('#datasetTab-button .pkpBadge').contains('2');
    });
    it('Author can delete research data in workflow', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();

        cy.get('#deleteDatasetButton').click();
        cy.contains('Are you sure you want to permanently delete the research data related to this submission?');
		cy.get('.modal__panel button:contains("Delete")').click();
        cy.contains('No research data transferred.', {timeout: 30000});
        cy.get('#dataStatement-button').click();
		cy.get('input[name="researchDataSubmitted"]').should('not.be.checked');
    });
    it('Author can upload research data in workflow', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();

        cy.contains('button', 'Upload research data').click();
        cy.contains('button', 'Add research data').click();
        cy.fixture('example.json', 'utf8').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'example.json',
				mimeType: 'application/json',
				encoding: 'utf8',
			});
		});
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
        cy.get('#datasetMetadata-datasetLanguage-control').select('English');
        cy.get('#datasetMetadata-datasetSubject-control').select('Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
        cy.get('#datasetMetadata-datasetRelationType-control').select('Is Cited By');
        cy.get('button:visible:contains("Save")').click();

		cy.contains('It is mandatory to send a README file, in PDF, MD or TXT format, to accompany the research data files');
        cy.contains('button', 'Add research data').click();
        cy.fixture('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'base64').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'README.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64'
			});
		});
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
        cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
        cy.get('button:visible:contains("Save")').click();
        cy.contains('h1', 'Research data', {timeout: 30000});
    });
    it('Check author actions were registered in activity log', function () {
		cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

		cy.contains('Activity Log').click();
		cy.get('#submissionHistoryGridContainer').within(() => {
            cy.get('tr:contains(File "Planilha_de_dados_ÇÕÔÁÀÃ.json" added as research data.) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(File "LEIAME.pdf" added as research data.) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(Research data deposited) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(Research data metadata updated) td').should('contain', 'Elinor Ostrom');
            cy.get('tr:contains(File "example.json" added as research data.) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(File "example.json" deleted from research data.) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(Research data deleted) td').should('contain', 'Elinor Ostrom');
		});
	});
    it('Author can not perform actions without edit permission granted', function () {
		if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
			cy.changeAuthorEditPermissionOnPublication('dbarnes', 'Elinor Ostrom', 'publicknowledge', submissionData.title,'uncheck');
		}
        
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();

		assertAdditionalInstructionsDisplay();
        cy.contains('Delete').should('be.disabled');
		cy.get('#dataset_metadata > form button[label="Save"]').should('be.disabled');

		cy.get('#dataset_files-button').click();
		cy.contains('Add research data').should('be.disabled');

		cy.get('#datasetFiles .listPanel__item button:contains(Delete)').should('be.disabled');
	});
});
