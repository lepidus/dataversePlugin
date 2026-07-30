import '../support/commands.js';

describe('Dataverse Plugin - Features around review stage', function () {
	let submissionData;
	let controlledDataversePid;
    
    before(function () {
		submissionData = {
			title: 'The importance of art for human well-being',
			abstract: 'Recent evidence show that art can have a great impact in improving mental well-being.',
			keywords: [
                'art',
			]
		}

		const controlledDataverseHost = '127.0.0.1:8099';
		const controlledDataverseRouter = 'plugins/generic/dataverse/tests/fixtures/controlledDataverse/router.php';
		Cypress.env('controlledDataverseUrl', `http://${controlledDataverseHost}/dataverse/testDataverse`);

		cy.exec(
			`nohup php -S ${controlledDataverseHost} ${controlledDataverseRouter}`
				+ ' >/tmp/dataverse-controlled-cypress.log 2>&1 & echo $!',
			{timeout: 10000}
		).then((result) => {
			controlledDataversePid = result.stdout.trim();
			expect(controlledDataversePid).to.match(/^\d+$/);
			cy.request({
				url: `http://${controlledDataverseHost}/reset`,
				method: 'POST',
				headers: {'X-Dataverse-key': 'valid-token'},
			});
		});

		cy.login('dbarnes', null, 'publicknowledge');
		cy.configureDataverse({
			url: Cypress.env('controlledDataverseUrl'),
			apiToken: 'valid-token',
			termsOfUse: 'https://example.test/terms',
			datasetPublish: 1,
		});
		cy.logout();
	});

	after(function () {
		cy.logout();
		cy.login('dbarnes', null, 'publicknowledge');
		cy.configureDataverse({
			url: Cypress.env('dataverseUrl'),
			apiToken: Cypress.env('dataverseApiToken'),
			termsOfUse: Cypress.env('dataverseTermsOfUse'),
			datasetPublish: 2,
		});
		cy.logout();
		cy.then(() => {
			if (controlledDataversePid && /^\d+$/.test(controlledDataversePid)) {
				cy.exec(`kill ${controlledDataversePid}`);
			}
		});
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
			cy.addKeyword('#titleAbstract-keywords-control-en', '#titleAbstract-keywords-selected-en', keyword);
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
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
        
        cy.contains('button', 'Add research data').click();
        cy.fixture('example.json', 'utf8').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').attachFile({
				fileContent,
				fileName: 'Raw_data.json',
				mimeType: 'application/json',
				encoding: 'utf8',
			});
		});
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
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();

        cy.get('select[name="datasetSubject"]').select('Arts and Humanities');
        cy.get('select[name="datasetLicense"]').select('CC BY 4.0');
        cy.advanceSubmissionSteps(1);

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
        cy.contains('span', 'Raw_data.json');
        cy.contains('span', 'README.pdf');

        cy.contains('span', 'Raw_data.json').parent().within(() => {
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
		cy.contains('a', 'Raw_data.json');
        cy.contains('a', 'README.pdf');
        cy.contains('a', 'example.json').should('not.exist');
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
		cy.get('input[name="termsOfUse"]').check();
		cy.get('form:visible button:contains("Save")').click();
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
		cy.get('#datasetMetadata-datasetSubject-control').select('Other');
		cy.get('#datasetMetadata-datasetLicense-control').select('CC0 1.0');
		cy.get('button:visible:contains("Save")').click();
        cy.contains('h1', 'Research data', {timeout: 30000});
    });
    it('Research data is published on submission acceptance', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

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
        cy.contains('has been accepted for publication and sent to the copyediting stage');
        cy.get('a.pkpButton').contains('View All Submissions').click();
        cy.findSubmission('myQueue', submissionData.title);

        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();

		cy.get('p:contains("Controlled Dataverse, V1")');
    });
});
