import '../support/commands.js';

function advanceNSteps(n) {
    for (let stepsAdvanced = 0; stepsAdvanced < n; stepsAdvanced++) {
        cy.contains('button', 'Continue').click();
        cy.wait(200);
    }
}

describe('Dataverse Plugin - Submission wizard features', function () {
	let submissionData;

	before(function () {
		submissionData = {
			title: 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation',
			abstract: 'Mass public transportation can be used as a way to reduce greenhouse gases emissions.',
			keywords: [
                'mass public transport',
				'sustainable cities',
			]
		}
	});

    function beginSubmission(submissionData) {
        cy.get('input[name="locale"][value="en"]').click();
        cy.setTinyMceContent('startSubmission-title-control', submissionData.title);
        
        if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
            cy.get('input[name="sectionId"][value="1"]').click();
        }
        
        cy.get('input[name="submissionRequirements"]').check();
        cy.get('input[name="privacyConsent"]').check();
        cy.contains('button', 'Begin Submission').click();
    }

    it('Begins submission. Checks for data statement fields', function () {
        cy.login('eostrom', null, 'publicknowledge');
        
        cy.get('div#myQueue a:contains("New Submission")').click();
        beginSubmission(submissionData);

        cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        submissionData.keywords.forEach(keyword => {
            cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
            cy.wait(500);
            cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
        });

        cy.contains('h2', 'Data statement');
        cy.get('#dataStatement-dataStatementUrls-control').should('not.be.visible');
        cy.get('#dataStatement-dataStatementReason-control-en').should('not.be.visible');

        cy.get('input[name="dataStatementTypes"][value=2]').click();
		cy.contains('Insert the URLs to the data');
        cy.get('#dataStatement-dataStatementUrls-control').should('be.visible');
		advanceNSteps(4);
        cy.contains('h3', 'Data statement');
		cy.contains('It is required to inform the URLs to the data in repositories');

        cy.get('.pkpSteps__step__label:contains("Details")').click();
		cy.get('#dataStatement-dataStatementUrls-control').type('Example text');
        cy.get('#dataStatement-dataStatementUrls-control').type('{enter}', {delay: 0});
        cy.contains('This is not a valid URL.');

        cy.get('#dataStatement-dataStatementUrls-control').type('https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
        cy.get('#dataStatement-dataStatementUrls-control').type('{enter}', {delay: 0});
        cy.contains('This is not a valid URL.').should('not.exist');

        cy.get('input[name="dataStatementTypes"][value=5]').click();
        cy.contains('Provide the justification for the unavailability of the data');
		cy.get('#dataStatement-dataStatementReason-control-en').should('be.visible');
		advanceNSteps(4);
		cy.contains('It is required to inform the justification for the unavailability of the data');

		cy.get('.pkpSteps__step__label:contains("Details")').click();
		cy.get('#dataStatement-dataStatementReason-control-en').clear().type('Has sensitive data', {delay: 0});
        advanceNSteps(4);
        
        cy.contains('li', 'The research data is available in one or more data repository(ies)');
        cy.contains('li', ' The research data cannot be made publicly available ');
        cy.contains('a', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
        cy.contains('Has sensitive data');
        cy.get('h3:contains("Data statement")').parent().parent().within(() => {
            cy.get('.pkpNotification--warning').should('not.exist');
        });
    });
    it('Shows dataset fields only when submission to Dataverse is chosen', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        advanceNSteps(1);
        cy.contains('h2', 'Research data').should('not.be.visible');
        advanceNSteps(2);
        cy.contains('h2', 'Research data metadata').should('not.be.visible');
        advanceNSteps(1);
        cy.contains('h3', 'Research data').should('not.exist');
        cy.contains('h3', 'Research data metadata').should('not.exist');

        cy.get('.pkpSteps__step__label:contains("Details")').click();
        cy.contains('The research data will be sent in subsequent stages of this submission, so that it can be deposited in the repository');
		cy.contains('a', 'Dataverse de Exemplo Lepidus');
        cy.get('input[name="dataStatementTypes"][value=3]').click();
        advanceNSteps(1);

        cy.contains('h2', 'Research data');
        cy.contains('Additional instructions about research data submission');
        advanceNSteps(2);
        cy.contains('h2', 'Research data metadata');
        advanceNSteps(1);
        cy.contains('h3', 'Research data');
        cy.contains('h3', 'Research data metadata');
    });
    it('Adds dataset files', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        advanceNSteps(1);

        cy.contains('h2', 'Research data');
        cy.contains('Use this field only for submitting research data');
        advanceNSteps(3);
        cy.contains('h3', 'Research data');
        cy.contains("To submit research data, it is necessary to send at least one file");

        cy.get('.pkpSteps__step__label:contains("Upload Files")').click();
        cy.uploadSubmissionFiles([{
			'file': 'dummy.pdf',
			'fileName': 'dummy.pdf',
			'mimeType': 'application/pdf',
			'genre': 'Article Text'
		}]);

        cy.contains('button', 'Add research data').click();
        cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'Data_detailing.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
        cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
        cy.get('#datasetFiles').contains('Data_detailing.pdf');
        
        cy.contains('button', 'Add research data').click();
        cy.fixture('../../plugins/generic/dataverse/cypress/fixtures/dummy.csv', 'utf8').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'Planilha_de_dados_ÇÕÔÁÀÃ.csv',
				mimeType: 'text/csv',
				encoding: 'utf8',
			});
		});
		cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
        cy.get('#datasetFiles').contains('a', 'Data_detailing.pdf');
		cy.get('#datasetFiles').contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.csv');

        advanceNSteps(3);
        cy.get('div:contains("To submit research data, it is necessary to send at least one file")').should('not.exist');
        cy.contains('Research data and galley have the same file');
        cy.contains('a', 'Data_detailing.pdf');
        cy.contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.csv');

        cy.get('.pkpSteps__step__label:contains("Upload Files")').click();
        cy.get('.listPanel__item:contains(Data_detailing.pdf) button:contains(Delete)').click();
        cy.contains('Are you sure you want to permanently delete the research data file Data_detailing.pdf?');
		cy.get('.modal__panel--dialog button:contains("Delete File")').click();
        cy.waitJQuery();
        cy.get('#datasetFiles').should('not.include.text', 'Data_detailing.pdf');
        
        advanceNSteps(3);
        cy.get('a:contains("Data_detailing.pdf")').should('not.exist');
        cy.get('div:contains("Research data and galley have the same file")').should('not.exist');
        cy.contains('It is mandatory to send a README file, in PDF, MD or TXT format, to accompany the research data files');
        cy.contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.csv');

        cy.get('.pkpSteps__step__label:contains("Upload Files")').click();
        cy.contains('button', 'Add research data').click();
        cy.fixture('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'base64').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'README.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
		cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
		cy.get('#datasetFiles').contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.csv');
        cy.get('#datasetFiles').contains('a', 'README.pdf');

        advanceNSteps(3);
        cy.get('div:contains("It is mandatory to send a README file, in PDF, MD or TXT format, to accompany the research data files")').should('not.exist');
        cy.contains('a', 'README.pdf');
        cy.contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.csv');
    });
    it('Adds dataset metadata', function () {
        cy.login('eostrom', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        advanceNSteps(3);
        cy.contains('h2', 'Research data metadata');
        cy.contains('Please provide the following details about the research data you are submitting');
        cy.contains('Research Data Subject');
        cy.contains('Research Data License');
        cy.get('select[name="datasetLicense"]').should('have.value', 'CC0 1.0');

        advanceNSteps(1);
        cy.contains('h3', 'Research data metadata');
        cy.contains('Research Data Subject');
        cy.contains('Research Data License');
        cy.contains('The subject of the research data is required');
        
        cy.get('.pkpSteps__step__label:contains("For the Editors")').click();
        cy.get('select[name="datasetSubject"]').select('Earth and Environmental Sciences');
        cy.get('select[name="datasetLicense"]').select('CC BY 4.0');

        advanceNSteps(1);
        cy.get('div:contains("The subject of the research data is required")').should('not.exist');
        cy.contains('Earth and Environmental Sciences');
        cy.contains('CC BY 4.0');

        cy.contains('button', 'Submit').click();
        cy.get('.modal__panel:visible').within(() => {
            cy.contains('button', 'Submit').click();
        });
        cy.wait(7000);
        cy.contains('h1', 'Submission complete');
    });
});