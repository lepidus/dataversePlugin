import '../support/commands.js';


describe('Research data deposit', function () {
	const currentYear = new Date().getFullYear();
	let submission;
	let dataverseName;
	let dataverseServerName;

	before(function () {
		submission = {
			id: 0,
			section: 'Articles',
			title: 'The Rise of the Machine Empire',
			abstract: 'An example abstract.',
			keywords: ['Modern History'],
		}
	});

	it('Check research data deposit in submission wizard', function () {
		cy.login('ckwantes', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();

		if (Cypress.env('contextTitles').en_US == 'Journal of Public Knowledge') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
		}

		cy.get('input[id^="dataStatementTypes"][value=1]').click();
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();
		cy.get('#submitStep1Form button.submitFormButton').click();

		cy.get('#submitStep2Form button.submitFormButton').click();

		cy.get('input[id^="title-en_US-"').type(submission.title, { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), submission.abstract);
		});
		cy.get('ul[id^="en_US-keywords-"]').then((node) => {
			submission.keywords.forEach((keyword) => {
				node.tagit('createTag', keyword);
			});
		});
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.get('#submitTabs a:contains("1. Start")').click();
		cy.get('input[id^="dataStatementTypes"][value=3]').click();
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();
		cy.get('#submitStep1Form button.submitFormButton').click();

		cy.get('button').contains('Finish Submission').click();
		cy.get('button.pkpModalConfirmButton').click();
		cy.get('div:contains("To submit research data, it is necessary to send at least one file.")');
		cy.get('div:contains("Please ensure that you have chosen and submitted research data files in step 2 of the submission.")');

		cy.get('#submitTabs a:contains("2. Upload Submission")').click();
		cy.get('#submitStep2Form button.submitFormButton').click();
		cy.get('div:contains("Research data is required. Please ensure that you have chosen and uploaded research data.")');

		cy.contains('Add research data').click();
		cy.wait(1000);
		cy.fixture('dummy.pdf', { encoding: 'base64' }).then((fileContent) => {
			cy.get('#uploadForm input[type=file]')
				.upload({
					fileContent,
					fileName: 'Data Table.pdf',
					mimeType: 'application/pdf',
					encoding: 'base64',
				});
		});
		cy.wait(200);
		cy.get('label a:contains(Terms of Use)').should('have.attr', 'href', Cypress.env('dataverseTermsOfUse'));
		cy.get('label:contains(Terms of Use) strong').then($strong => {
			dataverseName = $strong.text();
		});
		cy.get('#uploadForm button').contains('OK').click();
		cy.get('label[for="termsOfUse"]').should('contain', 'This field is required');
		cy.get('input[name="termsOfUse"').check();
		cy.get('#uploadForm button').contains('OK').click();
		cy.location('search').then(search => {
			submission.id = parseInt(search.split('=')[1], 10);
		});
		cy.get('#submitStep2Form button.submitFormButton').click();

		cy.get('button').contains('Finish Submission').click();
		cy.get('button.pkpModalConfirmButton').click();
		cy.get('div:contains("The subject of the research data is required.")');
		cy.get('div:contains("Please ensure that you have chosen the subject of the research data in step 3 of the submission.")');

		cy.get('#submitTabs a:contains("3. Enter Metadata")').click();
		cy.get('select[id^="datasetSubject"').select('Other');

		cy.contains('Research Data License');
		cy.get('select[id^="datasetLicense"').should('have.value', 'CC0 1.0');
		cy.get('select[id^="datasetLicense"').select('CC BY 4.0');
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.get('form[id=submitStep4Form]').find('button').contains('Finish Submission').click();
		cy.get('button.pkpModalConfirmButton').click();

		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');

		cy.contains('Review this submission').click();
		cy.get('button[aria-controls="publication"]').click();

		cy.get('button[aria-controls="dataStatement"]').click();
		cy.get('#dataStatement input[name="researchDataSubmitted"]').should('be.checked');

		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('#datasetData .value').should('contain', `Kwantes, Catherine, ${currentYear}, "The Rise of the Machine Empire"`);
		cy.get('#datasetData .value p').then((citation) => {
			dataverseServerName = citation.text().split(',')[5].trim();
		});
	});
	it('Check if options are disabled for authors without edit permission', function () {
		cy.login('ckwantes', null, 'publicknowledge');
		cy.visit('index.php/publicknowledge/authorDashboard/submission/' + submission.id);

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();

		cy.contains('Delete research data').should('be.disabled');
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]').should('be.disabled');

		cy.get('button[aria-controls="dataset_files"]').click();
		cy.contains('Add research data').should('be.enabled');

		cy.get('#datasetFiles .listPanel__item button:contains(Delete)').should('be.disabled');
	});
	it('Check author can edit research data metadata', function () {
		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.allowAuthorToEditPublication('dbarnes', null, 'Catherine Kwantes');
		}

		cy.login('ckwantes', null, 'publicknowledge');
		cy.visit('index.php/publicknowledge/authorDashboard/submission/' + submission.id);

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();

		cy.get('input[id^="datasetMetadata-datasetTitle-control"').clear();
		cy.get('input[id^="datasetMetadata-datasetTitle-control"').type('The Power of Computer Vision: Advances, Applications and Challenges', { delay: 0 });
		cy.get('div[id^="datasetMetadata-datasetDescription-control"').clear();
		cy.get('div[id^="datasetMetadata-datasetDescription-control"').type('Computer vision is an area of computer science that aims to enable machines to "see" and understand the world around them.', { delay: 0 });
		cy.get('#datasetMetadata-datasetKeywords-selected span:contains(Modern History) button').click();
		cy.get('#datasetMetadata-datasetKeywords-control').type('Computer Vision', { delay: 0 });
		cy.wait(500);
		cy.get('#datasetMetadata-datasetKeywords-control').type('{enter}', { delay: 0 });
		cy.get('#datasetMetadata-datasetSubject-control').select('Computer and Information Science');
		cy.get('#datasetMetadata-datasetLicense-control').should('have.value', 'CC BY 4.0');
		cy.get('#datasetMetadata-datasetLicense-control').select('CC0 1.0');
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]').click();
		cy.get('#datasetTab [role="status"]').contains('Saved');

		cy.get('#datasetData .value').contains('The Power of Computer Vision: Advances, Applications and Challenges');
	});

	it('Check author can edit research data files', function () {
		cy.login('ckwantes', null, 'publicknowledge');
		cy.visit('index.php/publicknowledge/authorDashboard/submission/' + submission.id);

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('button[aria-controls="dataset_files"]').click();

		cy.get('button').contains('Add research data').click();
		cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').upload({
				fileContent,
				fileName: 'samples.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
		cy.get('input[name="termsOfUse"').check();
		cy.get('[data-modal="fileForm"] button:contains("Save")').click();
		cy.get('#datasetFiles .listPanel__items').contains('samples.pdf');
		cy.get('#datasetTab-button .pkpBadge').contains('2');

		cy.get('.listPanel__item:contains(samples.pdf) button:contains(Delete)').click();
		cy.get('#datasetFiles .listPanel__items').contains('samples.pdf');
		cy.get('[data-modal="delete"] button:contains(Yes)').click();
		cy.waitJQuery();
		cy.get('#datasetFiles .listPanel__items').should('not.include.text', 'samples.pdf');
		cy.get('#datasetTab-button .pkpBadge').contains('1');
	});

	it('Check author can delete research data', function () {
		cy.login('ckwantes', null, 'publicknowledge');
		cy.visit('index.php/publicknowledge/authorDashboard/submission/' + submission.id);

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.contains('Delete research data').click();
		cy.get('[data-modal="delete"] button').contains('Yes').click();
		cy.contains('No research data transferred.');

		cy.get('button[aria-controls="dataStatement"]').click();
		cy.get('#dataStatement input[name="researchDataSubmitted"]').should('not.be.checked');
	});

	it('Check author actions was registered in activity log', function () {
		cy.findSubmissionAsEditor('dbarnes', null, 'Kwantes');

		cy.contains('Activity Log').click();
		cy.get('#submissionHistoryGridContainer tr:contains(Research data deposited) td').should('contain', 'Catherine Kwantes');
		cy.get('#submissionHistoryGridContainer tr:contains(Research data metadata updated) td').should('contain', 'Catherine Kwantes');
		cy.get('#submissionHistoryGridContainer tr:contains(File "samples.pdf" added as research data.) td').should('contain', 'Catherine Kwantes');
		cy.get('#submissionHistoryGridContainer tr:contains(File "samples.pdf" deleted from research data.) td').should('contain', 'Catherine Kwantes');
		cy.get('#submissionHistoryGridContainer tr:contains(Research data deleted) td').should('contain', 'Catherine Kwantes');
	});

	it('Check research data can be deposit in research data tab', function () {
		cy.findSubmissionAsEditor('dbarnes', null, 'Kwantes');

		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.get('button[aria-controls="workflow"]').click();
			cy.sendToReview();
			cy.assignReviewer('Julie Janssen');
			cy.recordEditorialDecision('Accept Submission');
			cy.recordEditorialDecision('Send To Production');
			cy.get('li.ui-state-active a:contains("Production")');
			cy.get('button[id="publication-button"]').click();
			cy.get('div#publication button:contains("Schedule For Publication")').click();
			cy.wait(1000);
			cy.get('select[id="assignToIssue-issueId-control"]').select('1');
			cy.get('div[id^="assign-"] button:contains("Save")').click();
			cy.get('div:contains("All publication requirements have been met. This will be published immediately in Vol. 1 No. 2 (2014). Are you sure you want to publish this?")');

		} else {
			cy.get('#publication-button').click();
			cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton').click();
		}

		cy.get('div.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('button').contains('Upload research data').should('not.exist');
		cy.get('button:contains("Unpublish"), button:contains("Unpost")').click();
		cy.get('div[data-modal="confirmUnpublish"] button:contains("Unpublish"), div[data-modal="confirmUnpublish"] button:contains("Unpost")').click();
		cy.wait(1000);

		cy.get('button').contains('Upload research data').click();
		cy.wait(1000);
		cy.contains('Add research data').click();
		cy.wait(1000);
		cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
			cy.get('[data-modal="fileForm"] input[type=file]').upload({
				fileContent,
				fileName: 'Data Table.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
		cy.wait(200);
		cy.get('input[name="termsOfUse"').check();
		cy.get('[data-modal="fileForm"] form button').contains('Save').click();
		cy.wait(200);
		cy.get('select[id^="datasetMetadata-datasetSubject-control"').select('Other');
		cy.get('select[id^="datasetMetadata-datasetLicense-control"').select('CC0 1.0');
		cy.get('#datasetTab form button').contains('Save').click();
		cy.get('#datasetTab [role="status"]').contains('Saved');

		cy.get('#datasetData .value').should('contain', `Kwantes, Catherine, ${currentYear}, "The Rise of the Machine Empire"`);

		cy.get('button[aria-controls="dataStatement"]').click();
		cy.get('#dataStatement input[name="researchDataSubmitted"]').should('be.checked');
	});

	it('Check editor can edit research data metadata', function () {
		cy.findSubmissionAsEditor('dbarnes', null, 'Kwantes');

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();

		cy.get('input[id^="datasetMetadata-datasetTitle-control"').clear();
		cy.get('input[id^="datasetMetadata-datasetTitle-control"').type('The Power of Computer Vision: Advances, Applications and Challenges', { delay: 0 });
		cy.get('div[id^="datasetMetadata-datasetDescription-control"').clear();
		cy.get('div[id^="datasetMetadata-datasetDescription-control"').type('Computer vision is an area of computer science that aims to enable machines to "see" and understand the world around them.', { delay: 0 });
		cy.get('#datasetMetadata-datasetKeywords-selected span:contains(Modern History) button').click();
		cy.get('#datasetMetadata-datasetKeywords-control').type('Computer Vision', { delay: 0 });
		cy.wait(500);
		cy.get('#datasetMetadata-datasetKeywords-control').type('{enter}', { delay: 0 });
		cy.get('#datasetMetadata-datasetSubject-control').select('Computer and Information Science');
		cy.get('#datasetMetadata-datasetLicense-control').should('have.value', 'CC0 1.0');
		cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]').click();
		cy.get('#datasetTab [role="status"]').contains('Saved');

		cy.get('#datasetData .value').contains('The Power of Computer Vision: Advances, Applications and Challenges');
	});

	it('Check editor can edit research data files', function () {
		cy.findSubmissionAsEditor('dbarnes', null, 'Kwantes');

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('button[aria-controls="dataset_files"]').click();

		cy.get('button').contains('Add research data').click();
		cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').upload({
				fileContent,
				fileName: 'samples.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
		cy.get('input[name="termsOfUse"').check();
		cy.get('[data-modal="fileForm"] button:contains("Save")').click();
		cy.get('#datasetFiles .listPanel__items').contains('samples.pdf');
		cy.get('#datasetTab-button .pkpBadge').contains('2');

		cy.get('.listPanel__item:contains(samples.pdf) button:contains(Delete)').click();
		cy.get('#datasetFiles .listPanel__items').contains('samples.pdf');
		cy.get('[data-modal="delete"] button:contains(Yes)').click();
		cy.waitJQuery();
		cy.get('#datasetFiles .listPanel__items').should('not.include.text', 'samples.pdf');
		cy.get('#datasetTab-button .pkpBadge').contains('1');
	});

	it('Check editor can delete research data', function () {
		cy.findSubmissionAsEditor('dbarnes', null, 'Kwantes');

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.contains('Delete research data').click();
		cy.setTinyMceContent('deleteDataset-deleteMessage-control', 'Your research data has been deleted.');
		cy.get('#deleteDataset-deleteMessage-control').click();
		cy.get('[data-modal="deleteDataset"] button').contains('Delete and send email').click();
		cy.contains('No research data transferred.');

		cy.get('button[aria-controls="dataStatement"]').click();
		cy.get('#dataStatement input[name="researchDataSubmitted"]').should('not.be.checked');
	});

	it('Check editor can publish research data', function () {
		let representation = (Cypress.env('contextTitles').en_US === 'Public Knowledge Preprint Server') ? 'preprint' : 'article';
		cy.findSubmissionAsEditor('dbarnes', null, 'Kwantes');

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();

		cy.get('button').contains('Upload research data').click();
		cy.wait(1000);
		cy.contains('Add research data').click();
		cy.wait(1000);
		cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
			cy.get('[data-modal="fileForm"] input[type=file]').upload({
				fileContent,
				fileName: 'Data Table.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
		cy.wait(200);
		cy.get('input[name="termsOfUse"').check();
		cy.get('[data-modal="fileForm"] form button').contains('Save').click();
		cy.wait(200);
		cy.get('select[id^="datasetMetadata-datasetSubject-control"').select('Other');
		cy.get('select[id^="datasetMetadata-datasetLicense-control"').select('CC BY 4.0');
		cy.get('#datasetTab form button').contains('Save').click();
		cy.get('#datasetTab [role="status"]').contains('Saved');

		cy.get('div#publication button:contains("Schedule For Publication"), div#publication button:contains("Post")').click();
		cy.get('div.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();
		cy.get('div[id^=publish').contains(/This submission contains deposited research data that is not yet public: https:\/\/doi\.org\/10\.[^\/]*\/.{3}\/.{6}/);
		cy.get('div[id^=publish').contains('In case you choose to publish them, make sure they are suitable for publication in');
		cy.get('div[id^=publish').contains('Would you like to publish the research data?');

		cy.get('input[name="shouldPublishResearchData"][value="1"]').parent().contains("Yes");
		cy.get('input[name="shouldPublishResearchData"][value="0"]').parent().contains("No");
		cy.get('input[name="shouldPublishResearchData"][value="1"]').should('not.be.checked');
		cy.get('input[name="shouldPublishResearchData"][value="0"]').should('not.be.checked');

		cy.get('input[name="shouldPublishResearchData"][value="1"]').click();
		cy.get('div.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('button').contains('Delete research data').should('be.disabled');
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]').should('be.disabled');
		cy.get('button').contains('Add research data').should('be.disabled');
		cy.get('#datasetFiles .listPanel__item .listPanel__itemActions button').should('be.disabled');

		cy.contains('View').click();

		cy.get('.label').contains('Research data');
		cy.get('.data_citation .value').contains(`Kwantes, Catherine, ${currentYear}, "The Rise of the Machine Empire"`);
		cy.get('.data_citation .value a').contains(/https:\/\/doi\.org\/10\.[^\/]*\/FK2\//);
		cy.get('.data_citation .value').contains(`${dataverseServerName}, V1`);
		cy.logout();

		cy.visit(`index.php/publicknowledge/${representation}/view/${submission.id}`);
		cy.get('.label').contains('Research data');
		cy.get('.data_citation .value').contains(`Kwantes, Catherine, ${currentYear}, "The Rise of the Machine Empire"`);
		cy.get('.data_citation .value a').contains(/https:\/\/doi\.org\/10\.[^\/]*\/FK2\//);
		cy.get('.data_citation .value').contains(`${dataverseServerName}, V1`);
	});

	it('Check editor actions was registered in activity log', function () {
		cy.login('dbarnes');
		cy.visit('/index.php/publicknowledge/workflow/access/' + submission.id);

		cy.contains('Activity Log').click();
		cy.get('#submissionHistoryGridContainer tr:contains(Research data deposited) td').should('contain', 'Daniel Barnes');
		cy.get('#submissionHistoryGridContainer tr:contains(Research data metadata updated) td').should('contain', 'Daniel Barnes');
		cy.get('#submissionHistoryGridContainer tr:contains(File "samples.pdf" added as research data.) td').should('contain', 'Daniel Barnes');
		cy.get('#submissionHistoryGridContainer tr:contains(File "samples.pdf" deleted from research data.) td').should('contain', 'Daniel Barnes');
		cy.get('#submissionHistoryGridContainer tr:contains(Research data deleted) td').should('contain', 'Daniel Barnes');
		cy.get('#submissionHistoryGridContainer tr:contains(Research data published) td').should('contain', 'Daniel Barnes');
	});

	it('Publish research data after submission published', function () {
		cy.login('cmontgomerie', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();

		if (Cypress.env('contextTitles').en_US == 'Journal of Public Knowledge') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select('Articles');
		}
		cy.get('input[id^="dataStatementTypes"][value=3]').click();
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();
		cy.get('button.submitFormButton').click();

		cy.contains('Add research data').click();
		cy.wait(1000);
		cy.fixture('dummy.pdf', { encoding: 'base64' }).then((fileContent) => {
			cy.get('#uploadForm input[type=file]')
				.upload({
					fileContent,
					fileName: 'Data.pdf',
					mimeType: 'application/pdf',
					encoding: 'base64',
				});
		});
		cy.wait(200);
		cy.get('input[name="termsOfUse"').check();
		cy.get('#uploadForm button').contains('OK').click();
		cy.get('#submitStep2Form button.submitFormButton').click();

		cy.get('input[id^="title-en_US-"').type('Submission with research data', { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), 'A test submission with research data deposited.');
		});
		cy.get('select[id^="datasetSubject"').select('Other');
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.waitJQuery();
		cy.get('#submitStep4Form button.submitFormButton').click();
		cy.get('button.pkpModalConfirmButton').click();

		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');

		cy.logout();

		cy.findSubmissionAsEditor('dbarnes', null, 'Montgomerie');
		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.get('button[aria-controls="workflow"]').click();
			cy.sendToReview();
			cy.assignReviewer('Julie Janssen');
			cy.recordEditorialDecision('Accept Submission');
			cy.recordEditorialDecision('Send To Production');
			cy.get('li.ui-state-active a:contains("Production")');
			cy.get('button[id="publication-button"]').click();
			cy.get('div#publication button:contains("Schedule For Publication")').click();
			cy.wait(1000);
			cy.get('select[id="assignToIssue-issueId-control"]').select('1');
			cy.get('div[id^="assign-"] button:contains("Save")').click();
			cy.get('div:contains("All publication requirements have been met. This will be published immediately in Vol. 1 No. 2 (2014). Are you sure you want to publish this?")');

		} else {
			cy.get('#publication-button').click();
			cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton').click();
		}
		cy.get('input[name="shouldPublishResearchData"][value="0"]').click();
		cy.get('div.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('button').contains('Publish research data').click();

		const publishMsg = 'Do you really want to publish the research data related to this submission? This action cannot be undone.'
			+ 'Before proceeding, make sure they are suitable for publication in '
			+ dataverseServerName;
		cy.get('div[data-modal="publish"]').contains(publishMsg);
		cy.get('div[data-modal="publish"] button').contains('Yes').click();

		cy.get('.value > p').contains('V1');
		cy.get('button').contains('Publish research data').should('not.exist');
		cy.get('button').contains('Delete research data').should('be.disabled');
		cy.get('button').contains('Add research data').should('be.disabled');
		cy.get('#dataset_metadata button').contains('Save').should('be.disabled');
	});
});
