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
			cy.allowAuthorToEditPublication('dbarnes', null, 'Elinor Ostrom');
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
        cy.get('#datasetMetadata-datasetDescription-control').should('have.value', submissionData.abstract);
        cy.get('#datasetMetadata-datasetKeywords-selected').within(() => {
            cy.contains(submissionData.keywords[0]);
            cy.contains(submissionData.keywords[1]);
        });
        cy.get('#datasetMetadata-datasetSubject-control').should('have.value', 'Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').should('have.value', 'CC BY 4.0');

        cy.get('#datasetMetadata-datasetTitle-control').clear().type('Test metadata editing', {delay: 0});
        cy.get('#datasetMetadata-datasetDescription-control').clear().type('new description', {delay: 0});
        cy.get('#datasetMetadata-datasetKeywords-control').type(submissionData.keywords[2], {delay: 0});
        cy.wait(500);
		cy.get('#datasetMetadata-datasetKeywords-control').type('{enter}', { delay: 0 });
        cy.get('#datasetMetadata-datasetSubject-control').select('Computer and Information Science');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC0 1.0');
        cy.get('button:visible:contains("Save")').click();
        cy.get('.pkpFormPage__status:contains("Saved")');

        cy.get('#datasetMetadata-datasetTitle-control').should('have.value', 'Test metadata editing');
        cy.get('#datasetMetadata-datasetDescription-control').should('have.value', 'new description');
        cy.get('#datasetMetadata-datasetKeywords-selected').within(() => {
            cy.contains(submissionData.keywords[2]);
        });
        cy.get('#datasetMetadata-datasetSubject-control').should('have.value', 'Computer and Information Science');
        cy.get('#datasetMetadata-datasetLicense-control').should('have.value', 'CC0 1.0');

        cy.get('#datasetMetadata-datasetTitle-control').clear().type('Replication data for: ' + submissionData.title, {delay: 0});
        cy.get('#datasetMetadata-datasetDescription-control').clear().type(submissionData.abstract, {delay: 0});
        cy.get('#datasetMetadata-datasetSubject-control').select('Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
        cy.get('button:visible:contains("Save")').click();
        cy.get('.pkpFormPage__status:contains("Saved")');
    });
    //Dataset files editing - Checks badge number here
    //Dataset deletion
    //Dataset adding
    //Actions were written in submission's activity log
    //Author can't perform actions without permissions granted
    //Checks options for publish dataset on submission publishing/posting - Editor
    //Checks can publish dataset after publishing (finally does it)
});