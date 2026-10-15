import {
    useDataverseFixtures,
    uniqueSubmission,
    createSubmissionFixture,
    trackWizardSubmission,
} from '../support/fixtures.js';

const dataFile = {
    path: 'example.json', name: 'Planilha_de_dados_ÇÕÔÁÀÃ.json', mime: 'application/json', encoding: 'utf8',
};
const readmeFile = {
    path: '../../plugins/generic/dataverse/cypress/fixtures/README.pdf',
    name: 'LEIAME.pdf', mime: 'application/pdf', encoding: 'base64',
};

function advanceNSteps(n) {
        cy.advanceSubmissionSteps(n);
    }

function assertAdditionalInstructionsDisplay() {
	cy.contains('1. Submit under "Research Data" any files that have been collected');
	cy.contains('2. It is mandatory to include a file named "Readme"/"Leiame"/"Leame"');
	cy.contains('For additional guidance on creating the file, consult the suggested references below');
	cy.contains('3. The files deposited in "Research Data" will form a dataset');
}

describe('Dataverse Plugin - Submission wizard features', function () {
	let submissionData;
    let fixture;
    const fixtures = useDataverseFixtures();

	beforeEach(function () {
		submissionData = {
			title: 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation',
			abstract: 'Mass public transportation can be used as a way to reduce greenhouse gases emissions.',
			keywords: [
                'mass public transport',
				'sustainable cities',
			]
		};
        submissionData = uniqueSubmission(submissionData);
	});


    function prepareDraft({manuscript = false, files = []} = {}) {
        createSubmissionFixture(fixtures, submissionData, {
            submitted: false, manuscript, statementTypes: files.length ? [3] : [2, 5],
        }).then((created) => { fixture = created; });
        if (files.length) {
            cy.get('.pkpSteps__step__label').contains('Upload Files').click();
            files.forEach((file) => {
                cy.uploadResearchDataFile(file.path, file.name, file.mime, file.encoding);
            });
            cy.get('.pkpSteps__step__label').contains('Details').click();
        }
    }

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
        cy.loginDataverseUser('eostrom');

        cy.get('div#myQueue a:contains("New Submission")').click();
        beginSubmission(submissionData);
        trackWizardSubmission(fixtures, submissionData).then((created) => { fixture = created; });

        cy.intercept("POST", /submissions\/\d+\/submit/).as('submissionValidation');

        cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        submissionData.keywords.forEach(keyword => {
            cy.addDataverseKeyword('#titleAbstract-keywords-control-en', keyword);
        });

        cy.contains('h2', 'Data statement');
        cy.get('#dataStatement-dataStatementUrls-control').should('not.be.visible');
        cy.get('#dataStatement-dataStatementReason-control-en').should('not.be.visible');
        cy.get('form[dataversepluginapiurl]').within(() => {
            cy.get('div.pkpFormLocales').should('not.be.visible');
        });

        cy.get('input[name="dataStatementTypes"][value=2]').click();
		cy.contains('Insert the URLs to the data');
        cy.get('#dataStatement-dataStatementUrls-control').should('be.visible');
		advanceNSteps(4);
        cy.wait('@submissionValidation').then((interception) => {
            assert.equal(interception.response.statusCode, 400);
            assert.property(interception.response.body, 'dataStatementUrls');
            assert.notProperty(interception.response.body, 'dataStatementReason');
        });
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
        cy.get('form[dataversepluginapiurl]').within(() => {
            cy.get('div.pkpFormLocales').should('be.visible');
        });
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
        cy.loginDataverseUser('eostrom');
        prepareDraft();

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
        assertAdditionalInstructionsDisplay();
        advanceNSteps(2);
        cy.contains('h2', 'Research data metadata');
        advanceNSteps(1);
        cy.contains('h3', 'Research data');
        cy.contains('h3', 'Research data metadata');
    });
    it('Should add dataset files. Dataset file should not be the same as the galley file', function () {
        cy.loginDataverseUser('eostrom');
        prepareDraft();
        cy.get('input[name="dataStatementTypes"][value=3]').check();
        advanceNSteps(1);

        cy.contains('h2', 'Research data');
        cy.contains('Use this field only for submitting research data');
        advanceNSteps(3);
        cy.contains('h3', 'Research data');
        cy.contains("To submit research data, it is necessary to send at least one file, accompanied by a README file");

        cy.get('.pkpSteps__step__label:contains("Upload Files")').click();
        cy.uploadSubmissionFiles([{
			'file': 'dummy.pdf',
			'fileName': 'dummy.pdf',
			'mimeType': 'application/pdf',
			'genre': 'Article Text'
		}]);

        cy.contains('button', 'Add research data').click();
        cy.intercept('POST', '**/api/v1/temporaryFiles*').as('uploadResearchData');
        cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'Data_detailing.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
        cy.wait('@uploadResearchData').its('response.statusCode').should('eq', 200);
        cy.get('[data-modal="addDatasetFileModal"] .pkpFormField--upload__fileName').should('contain', 'Data_detailing.pdf');
        cy.contains('legend', 'Dataverse terms of use');
        cy.contains('Deposit research data in Dataverse').within(() => {
            cy.contains('a', 'Terms of Use').invoke('attr', 'href').then((termsOfUseUrl) => {
                expect(termsOfUseUrl).to.equal(Cypress.env('dataverseTermsOfUse'));
            });
        });
		cy.saveResearchDataFile();
        cy.get('#datasetFiles').contains('Data_detailing.pdf');
        
        cy.uploadResearchDataFile('example.json', 'Planilha_de_dados_ÇÕÔÁÀÃ.json', 'application/json', 'utf8');
        cy.get('#datasetFiles').contains('a', 'Data_detailing.pdf');
		cy.get('#datasetFiles').contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.json');

        advanceNSteps(3);
        cy.get('div:contains("To submit research data, it is necessary to send at least one file, accompanied by a README file")').should('not.exist');
        cy.contains('Research data and galley have the same file');
        cy.contains('a', 'Data_detailing.pdf');
        cy.contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.json');

        cy.get('.pkpSteps__step__label:contains("Upload Files")').click();
        cy.get('.listPanel__item:contains(Data_detailing.pdf) button:contains(Delete)').click();
        cy.contains('Are you sure you want to permanently delete the research data file Data_detailing.pdf?');
		cy.get('.modal__panel--dialog button:contains("Delete File")').click();
        cy.get('#datasetFiles').should('not.include.text', 'Data_detailing.pdf');
        
        advanceNSteps(3);
        cy.get('a:contains("Data_detailing.pdf")').should('not.exist');
        cy.get('div:contains("Research data and galley have the same file")').should('not.exist');
    });
    it('Dataset should contain a README file and at least another file', function () {
        cy.loginDataverseUser('eostrom');
        prepareDraft({manuscript: true, files: [dataFile]});
        advanceNSteps(4);

        cy.contains('It is mandatory to send a README file, in PDF, MD or TXT format, to accompany the research data files');
        cy.contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.json');

        cy.get('.pkpSteps__step__label:contains("Upload Files")').click();
        cy.uploadResearchDataFile('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'LEIAME.pdf', 'application/pdf', 'base64');
        cy.get('#datasetFiles').contains('a', 'LEIAME.pdf');

        cy.get('.listPanel__item:contains(Planilha_de_dados_ÇÕÔÁÀÃ.json) button:contains(Delete)').click();
		cy.get('.modal__panel--dialog button:contains("Delete File")').click();
        cy.get('#datasetFiles').should('not.include.text', 'Planilha_de_dados_ÇÕÔÁÀÃ.json');

        advanceNSteps(3);
        cy.get('div:contains("It is mandatory to send a README file, in PDF, MD or TXT format, to accompany the research data files")').should('not.exist');
        cy.contains('The research data must contain at least one readable, non-empty file besides README files, either directly or inside a ZIP archive.');

        cy.get('.pkpSteps__step__label:contains("Upload Files")').click();
        cy.uploadResearchDataFile('example.json', 'Planilha_de_dados_ÇÕÔÁÀÃ.json', 'application/json', 'utf8');
        cy.get('#datasetFiles').contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.json');

        advanceNSteps(3);
        cy.get('div:contains("The research data must contain at least one readable, non-empty file besides README files, either directly or inside a ZIP archive.")').should('not.exist');
        cy.contains('a', 'LEIAME.pdf');
        cy.contains('a', 'Planilha_de_dados_ÇÕÔÁÀÃ.json');
    });
    it('Adds dataset metadata', function () {
        cy.loginDataverseUser('eostrom');
        prepareDraft({manuscript: true, files: [dataFile, readmeFile]});
        
        advanceNSteps(3);
        cy.contains('h2', 'Research data metadata');
        cy.contains('Please provide the following details about the research data you are submitting');
        cy.contains('Research Data Language');
        cy.contains('Research Data Subject');
        cy.contains('Research Data License');
        cy.contains('Research Data Relation Type');
        cy.get('select[name="datasetLanguage"]').should('have.value', 'English');
        cy.get('select[name="datasetLicense"]').should('have.value', 'CC0 1.0');
        cy.get('select[name="datasetRelationType"]').should('have.value', 'IsCitedBy');

        advanceNSteps(1);
        cy.contains('h3', 'Research data metadata');
        cy.contains('Research Data Language');
        cy.contains('Research Data Subject');
        cy.contains('Research Data License');
        cy.contains('Research Data Relation Type');
        cy.contains('The subject of the research data is required');
        
        cy.get('.pkpSteps__step__label:contains("For the Editors")').click();
        cy.get('select[name="datasetLanguage"]').select('French');
        cy.get('select[name="datasetSubject"]').select('Earth and Environmental Sciences');
        cy.get('select[name="datasetLicense"]').select('CC BY 4.0');
        cy.get('select[name="datasetRelationType"]').select('Is Supplemented By');

        advanceNSteps(1);
        cy.get('div:contains("The subject of the research data is required")').should('not.exist');
        cy.contains('French');
        cy.contains('Earth and Environmental Sciences');
        cy.contains('CC BY 4.0');
        cy.contains('Is Supplemented By')

        cy.then(() => { fixture.depositAttempted = true; });
        cy.submitResearchData();
        cy.then(() => { fixture.submitted = true; });
    });
});
