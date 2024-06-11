import '../support/commands.js';

describe('Dataverse Plugin - Features around review stage', function () {
	let submissionData;
    
    before(function () {
		submissionData = {
			title: 'The importance of art for human well-being',
			abstract: 'Recent evidence show that art can have a great impact in improving mental well-being.',
			keywords: [
                'art',
				'well-being',
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

    it('Creates new submission with research data', function () {
        cy.login('ckwantes', null, 'publicknowledge');
        
        cy.get('#myQueue a:contains("New Submission")').click();
        beginSubmission(submissionData);

        cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        submissionData.keywords.forEach(keyword => {
            cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
            cy.wait(500);
            cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
        });
        cy.get('input[name="dataStatementTypes"][value=3]').click();
        cy.contains('button', 'Continue').click();

        cy.uploadSubmissionFiles([{
			'file': 'dummy.pdf',
			'fileName': 'dummy.pdf',
			'mimeType': 'application/pdf',
			'genre': 'Article Text'
		}]);
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
        
        cy.contains('button', 'Add research data').click();
        cy.fixture('dummy.xlsx', 'base64').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'Raw_data.xlsx',
				mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				encoding: 'base64',
			});
		});
		cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();

        cy.get('select[name="datasetSubject"]').select('Arts and Humanities');
        cy.get('select[name="datasetLicense"]').select('CC BY 4.0');
        cy.contains('button', 'Continue').click();

        cy.contains('button', 'Submit').click();
        cy.get('.modal__panel:visible').within(() => {
            cy.contains('button', 'Submit').click();
        });
        cy.waitJQuery();
        cy.contains('h1', 'Submission complete');
    });
    /*it('Editor selects which data files will be available for reviewers', function () {

    });
    it('Selected data files are displayed for reviewers', function () {

    });
    it('Configures plugin to publish research data in editor decision', function () {

    });
    it('Research data is published on submission acceptance', function () {
        
    });*/
});