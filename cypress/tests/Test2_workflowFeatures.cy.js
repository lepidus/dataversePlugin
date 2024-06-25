import '../support/commands.js';

describe('Dataverse Plugin - Workflow features', function () {
	let submissionData;
    
    before(function () {
		submissionData = {
			title: 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation',
			abstract: 'Mass public transportation can be used as a way to reduce greenhouse gases emissions.',
			keywords: [
                'mass public transport',
				'sustainable cities',
                'climate change'
			]
		}
	});

    it('Data statement features are displayed in workflow tab', function () {
        if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
			cy.changeAuthorEditPermissionOnPublication('dbarnes', null, 'Elinor Ostrom', 'publicknowledge', 'check');
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

        cy.get('#datasetMetadata-datasetTitle-control').should('have.value', 'Replication data for: ' + submissionData.title);
        cy.getTinyMceContent('datasetMetadata-datasetDescription-control').should('include', submissionData.abstract);
        cy.get('#datasetMetadata-datasetKeywords-selected-en').within(() => {
            cy.contains(submissionData.keywords[0]);
            cy.contains(submissionData.keywords[1]);
        });
        cy.get('#datasetMetadata-datasetSubject-control').should('have.value', 'Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').should('have.value', 'CC BY 4.0');

        cy.get('#datasetMetadata-datasetTitle-control').clear().type('Test metadata editing', {delay: 0});
        cy.setTinyMceContent('datasetMetadata-datasetDescription-control', 'new description');
        cy.get('#datasetMetadata-datasetKeywords-control-en').type(submissionData.keywords[2], {delay: 0});
        cy.wait(500);
		cy.get('#datasetMetadata-datasetKeywords-control-en').type('{enter}', { delay: 0 });
        cy.get('#datasetMetadata-datasetSubject-control').select('Computer and Information Science');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC0 1.0');
        cy.get('button:visible:contains("Save")').click();
        cy.get('.pkpFormPage__status:contains("Saved")');

        cy.get('#datasetMetadata-datasetTitle-control').should('have.value', 'Test metadata editing');
        cy.getTinyMceContent('datasetMetadata-datasetDescription-control').should('include', 'new description');
        cy.get('#datasetMetadata-datasetKeywords-selected-en').within(() => {
            cy.contains(submissionData.keywords[2]);
        });
        cy.get('#datasetMetadata-datasetSubject-control').should('have.value', 'Computer and Information Science');
        cy.get('#datasetMetadata-datasetLicense-control').should('have.value', 'CC0 1.0');

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
        cy.get('#datasetTab-button').click();
        cy.get('#dataset_files-button').click();

		cy.get('#datasetFiles').contains('a', 'Raw_data.xlsx');
        cy.get('#datasetTab-button .pkpBadge').contains('1');

        cy.contains('button', 'Add research data').click();
        cy.fixture('example.json', 'utf8').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'example.json',
				mimeType: 'application/json',
				encoding: 'utf8',
			});
		});
        cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();

        cy.get('#datasetFiles').contains('example.json');
        cy.get('#datasetTab-button .pkpBadge').contains('2');

        cy.get('.listPanel__item:contains("example.json") button:contains("Delete")').click();
		cy.get('.modal__panel--dialog button:contains("Delete File")').click();
        cy.waitJQuery();

        cy.get('#datasetFiles').should('not.include.text', 'example.json');
        cy.get('#datasetTab-button .pkpBadge').contains('1');
    });
    it('Author can delete research data in workflow', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();
        
        cy.contains('Delete research data').click();
		cy.get('.modal__panel button:contains("Delete research data")').click();
        cy.wait(3000);
		
        cy.contains('No research data transferred.');
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
        cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();

        cy.get('#datasetMetadata-datasetSubject-control').select('Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
        cy.get('button:visible:contains("Save")').click();
        cy.wait(3000);

        cy.contains('h1', 'Research data');
    });
    it('Check author actions were registered in activity log', function () {
		cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

		cy.contains('Activity Log').click();
		cy.get('#submissionHistoryGridContainer').within(() => {
			cy.get('tr:contains(File "Data_detailing.pdf" added as research data.) td').should('contain', 'Elinor Ostrom');
            cy.get('tr:contains(File "Raw_data.xlsx" added as research data.) td').should('contain', 'Elinor Ostrom');
            cy.get('tr:contains(File "Data_detailing.pdf" deleted from research data.) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(Research data deposited) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(Research data metadata updated) td').should('contain', 'Elinor Ostrom');
            cy.get('tr:contains(File "example.json" added as research data.) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(File "example.json" deleted from research data.) td').should('contain', 'Elinor Ostrom');
			cy.get('tr:contains(Research data deleted) td').should('contain', 'Elinor Ostrom');
		});
	});
    it('Author can not perform actions without edit permission granted', function () {
		if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
			cy.changeAuthorEditPermissionOnPublication('dbarnes', null, 'Elinor Ostrom', 'publicknowledge', 'uncheck');
		}
        
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();

		cy.contains('Delete research data').should('be.disabled');
		cy.get('#dataset_metadata > form button[label="Save"]').should('be.disabled');

		cy.get('#dataset_files-button').click();
		cy.contains('Add research data').should('be.disabled');

		cy.get('#datasetFiles .listPanel__item button:contains(Delete)').should('be.disabled');
	});
    it('Editor can delete research data in workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);
        
        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();
        
        cy.contains('Delete research data').click();
        cy.getTinyMceContent('deleteDataset-deleteMessage-control')
            .should('include', 'The research data from the manuscript submission "' + submissionData.title + '" has been removed');
		cy.get('.modal__panel button:contains("Delete and send email")').click();
        cy.wait(3000);
		
        cy.contains('No research data transferred.');
        cy.get('#dataStatement-button').click();
		cy.get('input[name="researchDataSubmitted"]').should('not.be.checked');
    });
    it('Editor can upload research data in workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);
        
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
        cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();

        cy.get('#datasetMetadata-datasetSubject-control').select('Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
        cy.get('button:visible:contains("Save")').click();
        cy.wait(3000);

        cy.contains('h1', 'Research data');
    });
    it('Editor can publish dataset on submission publishing', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);
        
        if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
			cy.get('#workflow-button').click();
            
            cy.clickDecision('Send for Review');
            cy.contains('button', 'Skip this email').click();
            cy.contains('button', 'Continue').click();
			cy.contains('button', 'Record Decision').click();
            cy.get('a.pkpButton').contains('View Submission').click();
			cy.assignReviewer('Julie Janssen');
			
            cy.clickDecision('Accept Submission');
            cy.recordDecisionAcceptSubmission(['Elinor Ostrom'], [], []);
            
            cy.clickDecision('Send To Production');
            cy.recordDecisionSendToProduction(['Elinor Ostrom'], []);
			cy.isActiveStageTab('Production');
			
            cy.get('#publication-button').click();
			cy.get('div#publication button:contains("Schedule For Publication")').click();
			cy.wait(1000);
			cy.get('select[id="assignToIssue-issueId-control"]').select('1');
			cy.get('div[id^="assign-"] button:contains("Save")').click();
			cy.contains('All publication requirements have been met. This will be published immediately in Vol. 1 No. 2 (2014). Are you sure you want to publish this?');
		} else {
			cy.get('#publication-button').click();
			cy.get('div#publication button:contains("Post")').click();
		}

        cy.get('.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();
		cy.contains(/This submission contains deposited research data that is not yet public: https:\/\/doi\.org\/10\.[^\/]*\/.{3}\/.{6}/);
		cy.contains('In case you choose to publish them, make sure they are suitable for publication in');
		cy.contains('Would you like to publish the research data?');

		cy.get('input[name="shouldPublishResearchData"][value="1"]').parent().contains("Yes");
		cy.get('input[name="shouldPublishResearchData"][value="0"]').parent().contains("No");
		cy.get('input[name="shouldPublishResearchData"][value="1"]').should('not.be.checked');
		cy.get('input[name="shouldPublishResearchData"][value="0"]').should('not.be.checked');

        cy.get('input[name="shouldPublishResearchData"][value="0"]').click();
        cy.get('.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();
        cy.wait(1000);

        cy.get('.pkpPublication__statusPublished').should('have.text', 'Published');
    });
    it('Editor publishes dataset after submission publishing', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', submissionData.title);

        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();

        cy.get('button:contains("Publish research data")').click();
        
        const publishMsg = 'Do you really want to publish the research data related to this submission? This action cannot be undone.'
			+ 'Before proceeding, make sure they are suitable for publication in ';
		cy.get('div[data-modal="publishDataset"]').contains(publishMsg);
		cy.get('div[data-modal="publishDataset"] button:contains("Yes")').click();
		cy.wait(1000);

		cy.get('.value > p').contains('V1');
		cy.contains('Publish research data').should('not.exist');
		cy.get('button:contains("Delete research data")').should('be.disabled');
		cy.get('button:contains("Add research data")').should('be.disabled');
		cy.get('#dataset_metadata button:contains("Save")').should('be.disabled');
    });
    it('Publishing of submission new version do not publish dataset', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', submissionData.title);

        cy.get('#publication-button').click();
        cy.contains('button', 'Create New Version').click();
        cy.get('.modal__panel button:contains("Yes")').click();
        cy.wait(1000);

        cy.get('.pkpPublication__version:contains("2")');
        cy.contains('button', 'Publish').click();
        cy.contains('Would you like to publish the research data?').should('not.exist');
        cy.get('.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();
        cy.wait(1000);

        cy.get('.pkpPublication__statusPublished').should('have.text', 'Published');
        cy.get('#datasetTab-button').click();
        cy.get('.value > p').contains('Demo Dataverse, V1');
    });
});