describe('Research data publishing in editor decision', function () {
	let submission;
	let dataverseServerName;

	before(function () {
		if (Cypress.env('contextTitles').en_US !== 'Journal of Public Knowledge') {
			this.skip();
		}

		submission = {
			id: 0,
			section: 'Articles',
			title: 'The Rise of the Machine Empire',
			abstract: 'An example abstract.',
			keywords: ['Modern History'],
		}
	});

	it('Configures plugin to publish research data in editor decision', function () {
		const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin';

		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('a:contains("Website")').click();

		cy.waitJQuery();
		cy.get('button#plugins-button').click();
		cy.get('input[id^=select-cell-dataverseplugin]').check();
		cy.get('input[id^=select-cell-dataverseplugin]').should('be.checked');
		cy.get('tr#' + pluginRowId + ' a.show_extras').click();
		cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

		cy.get('input[name="datasetPublish"][value=1]').check({ force: true });
		cy.get('form#dataverseConfigurationForm button:contains("OK")').click();
		cy.get('div:contains("Your changes have been saved.")');
	});

	it('Makes a new submission with research data', function () {
		cy.login('zwoods', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();

		if (Cypress.env('contextTitles').en_US == 'Journal of Public Knowledge') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
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
					fileName: 'Data Table.pdf',
					mimeType: 'application/pdf',
					encoding: 'base64',
				});
		});
		cy.wait(200);
		cy.get('#uploadForm button').contains('OK').click();
		cy.get('label:contains(Terms of Use) strong').then($strong => {
			dataverseName = $strong.text();
		});
		cy.get('input[name="termsOfUse"').check();
		cy.get('#uploadForm button').contains('OK').click();
		cy.get('#submitStep2Form button.submitFormButton').click();

		cy.get('input[id^="title-en_US-"').type(submission.title, { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), submission.abstract);
		});
		cy.get('ul[id^="en_US-keywords-"]').then((node) => {
			node.tagit('createTag', submission.keywords[0]);
		});
		cy.get('select[id^="datasetSubject"').select('Other');
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.waitJQuery();
		cy.get('#submitStep4Form button.submitFormButton').click();
		cy.get('button.pkpModalConfirmButton').click();

		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
	});

	it('Check research data is published in editor decision', function () {
		cy.findSubmissionAsEditor('dbarnes', null, 'Woods');

		cy.get('ul.pkp_workflow_decisions:visible a:contains("Accept and Skip Review")', { timeout: 30000 }).click();
		cy.get('button:contains("Next:")').click();
		cy.get('#researchDataNotice').contains(/This submission contains deposited research data that is not yet public: https:\/\/doi\.org\/10\.[^\/]*\/.{3}\/.{6}/);
		cy.get('#researchDataNotice').contains('By accepting the submission, the data will be published in the Dataverse repository.')
		cy.get('#researchDataNotice').contains('Please make sure that the research data is suitable for publication in ');
		cy.get('button:contains("Record Editorial Decision")').click();
		cy.waitJQuery();

		cy.get('button[id="publication-button"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('#datasetData .value').contains('V1');
	});
});
