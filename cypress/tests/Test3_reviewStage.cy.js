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
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();

        cy.get('select[name="datasetSubject"]').select('Arts and Humanities');
        cy.get('select[name="datasetLicense"]').select('CC BY 4.0');
        cy.contains('button', 'Continue').click();
        cy.wait(500);

        cy.contains('button', 'Submit').click();
        cy.get('.modal__panel:visible').within(() => {
            cy.contains('button', 'Submit').click();
        });
        cy.waitJQuery();
        cy.contains('h1', 'Submission complete');
    });
    it('Editor selects which data files will be available for reviewers', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.get('#workflow-button').click();
        cy.clickDecision('Send for Review');

        cy.contains('h1', 'Send for Review');
        cy.contains('h2', 'Notify Authors');
        cy.contains('button', 'Skip this email').click();
        cy.contains('h2', 'Select Files');
        cy.contains('button', 'Continue').click();
        cy.contains('h2', 'Select Data Files');
        cy.contains('This submission has deposited research data. Please, select which data files will be made available for reviewers to view');
        cy.contains('span', 'example.json');
        cy.contains('span', 'Raw_data.xlsx');
        cy.contains('span', 'README.pdf');

        cy.contains('span', 'Raw_data.xlsx').parent().within(() => {
            cy.get('input').check();
        });
        cy.contains('span', 'README.pdf').parent().within(() => {
            cy.get('input').check();
        });

        cy.contains('button', 'Record Decision').click();
        cy.get('a.pkpButton').contains('View Submission').click();
        
        cy.assignReviewer('Julie Janssen');
    });
    it('Selected data files are displayed for reviewers', function () {
        cy.login('jjanssen', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.contains('h1', 'Review:');
        cy.contains('Data statement');
		cy.contains('The research data has been submitted to the Dataverse de Exemplo Lepidus repository');
		cy.contains('a', 'Raw_data.xlsx');
        cy.contains('a', 'README.pdf');
        cy.contains('a', 'example.json').should('not.exist');
    });
    it('Configures plugin to publish research data in editor decision', function () {
        const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin';

		cy.login('dbarnes', null, 'publicknowledge');
		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();
		cy.get('tr#' + pluginRowId + ' a.show_extras').click();
		cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

		cy.get('input[name="datasetPublish"][value=1]').focus().check();
		cy.get('#dataverseConfigurationForm button:contains("OK")').click();
		cy.contains('Your changes have been saved');
    });
    it('Deletes research data on submission declining', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.clickDecision('Decline Submission');
        cy.contains('h1', 'Decline Submission');
        cy.contains('h2', 'Notify Authors');
        cy.contains('button', 'Skip this email').click();
        cy.contains('h2', 'Research data');
        cy.contains(/This submission contains deposited research data: https:\/\/doi\.org\/10\.[^\/]*\/.{3}\/.{6}/);
		cy.contains('Would you like to delete the research data?');
        cy.contains('label', 'Yes').within(() => {
            cy.get('input').click();
        });

        cy.contains('button', 'Record Decision').click();
        cy.wait(1000);
        cy.contains('has been declined and sent to the archives');
        cy.get('a.pkpButton').contains('View All Submissions').click();
        
        cy.findSubmission('archive', submissionData.title);
        cy.contains('.pkpBadge', 'Declined');
        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();
        cy.contains('No research data transferred');
    });
    it('Reverts declining and adds research data again', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', submissionData.title);

        cy.contains('button', 'Change decision').click();
		cy.clickDecision('Revert Decline');
        cy.contains('h1', 'Revert Decline');
        cy.contains('h2', 'Notify Authors');
        cy.contains('button', 'Skip this email').click();
        cy.contains('button', 'Record Decision').click();
        cy.wait(1000);
        
        cy.contains('is now an active submission in the review stage');
        cy.get('a.pkpButton').contains('View All Submissions').click();

        cy.findSubmission('active', submissionData.title);
        cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();

		cy.contains('button', 'Upload research data').click();
        cy.contains('button', 'Add research data').click();
		cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'Data Table.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
		cy.wait(1000);
		cy.get('input[name="termsOfUse"').check();
		cy.get('form:visible button:contains("Save")').click();
        cy.contains('button', 'Add research data').click();
        cy.fixture('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'utf8').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'README.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64'
			});
		});
        cy.wait(1000);
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
		cy.get('#datasetMetadata-datasetSubject-control').select('Other');
		cy.get('#datasetMetadata-datasetLicense-control').select('CC0 1.0');
		cy.get('button:visible:contains("Save")').click();
		cy.wait(7000);

        cy.contains('h1', 'Research data', {timeout:10000});
    });
    it('Research data is published on submission acceptance', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.waitDatasetTabLoading();

        cy.get('#workflow-button').click();
        cy.clickDecision('Accept Submission');

        cy.contains('h1', 'Accept Submission');
        cy.contains('h2', 'Notify Authors');
        cy.contains('button', 'Skip this email').click();
        cy.contains('h2', 'Select Files');
        cy.contains('button', 'Continue').click();
        cy.contains('h2', 'Research data');
        cy.contains(/This submission contains deposited research data that is not yet public: https:\/\/doi\.org\/10\.[^\/]*\/.{3}\/.{6}/);
		cy.contains('In case you choose to publish them, make sure they are suitable for publication in');
		cy.contains('Would you like to publish the research data?');

        cy.contains('label', 'Yes').within(() => {
            cy.get('input').click();
        });

        cy.contains('button', 'Record Decision').click();
        cy.wait(1000);
        cy.contains('has been accepted for publication and sent to the copyediting stage');
        cy.get('a.pkpButton').contains('View All Submissions').click();
        cy.findSubmission('myQueue', submissionData.title);

        cy.waitDatasetTabLoading();

        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();

        cy.get('p:contains("Demo Dataverse, V1")');
    });
});