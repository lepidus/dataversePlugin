import '../support/commands.js';

describe('Research data publishing in editor decision', function () {
	let submission;

	before(function () {
		if (Cypress.env('contextTitles').en_US !== 'Journal of Public Knowledge') {
			this.skip();
		}

		submission = {
			id: 0,
			section: 'Articles',
			title: 'The second Machine Empire',
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
		cy.get('input[name="termsOfUse"').check();
		cy.get('#uploadForm button').contains('OK').click();
		cy.wait(1000);

		cy.contains('Add research data').click();
		cy.wait(1000);
		cy.fixture('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', { encoding: 'base64' }).then((fileContent) => {
			cy.get('#uploadForm input[type=file]')
				.upload({
					fileContent,
					fileName: 'README.pdf',
					mimeType: 'application/pdf',
					encoding: 'base64',
				});
		});
		cy.wait(200);
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
		cy.wait(7000);

		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
	});
	it('Deletes research data on submission declining', function () {
		cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submission.title);

		cy.contains('a', 'Decline Submission').click();
		cy.contains(/This submission contains deposited research data: https:\/\/doi\.org\/10\.[^\/]*\/.{3}\/.{6}/);
		cy.contains('Would you like to delete the research data?');
		
		cy.get('input[name="shouldDeleteResearchData"][value="1"]').parent().contains("Yes");
		cy.get('input[name="shouldDeleteResearchData"][value="0"]').parent().contains("No");
		cy.get('input[name="shouldDeleteResearchData"][value="1"]').should('not.be.checked');
		cy.get('input[name="shouldDeleteResearchData"][value="0"]').should('not.be.checked');
		
		cy.get('input[name="shouldDeleteResearchData"][value="1"]').click();
		cy.contains('button', 'Record Editorial Decision').click();
		cy.contains('.pkpBadge', 'Declined');

		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();
		cy.contains('No research data transferred.');
	});
	it('Reverts declining and adds research data again', function () {
		cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', submission.title);

		cy.contains('button', 'Change decision').click();
		cy.contains('a', 'Revert Decline').click();
		cy.contains('button', 'Revert Decline').click();
		cy.contains('.pkpBadge', 'Declined').should('not.exist');
		
		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();

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
		cy.get('input[name="termsOfUse"').check();
		cy.get('[data-modal="fileForm"] form button').contains('Save').click();
		cy.contains('Add research data').click();
		cy.wait(1000);
		cy.fixture('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'base64').then((fileContent) => {
			cy.get('[data-modal="fileForm"] input[type=file]').upload({
				fileContent,
				fileName: 'README.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64',
			});
		});
		cy.get('input[name="termsOfUse"').check();
		cy.get('[data-modal="fileForm"] form button').contains('Save').click();
		cy.get('select[id^="datasetMetadata-datasetSubject-control"').select('Other');
		cy.get('select[id^="datasetMetadata-datasetLicense-control"').select('CC0 1.0');
		cy.get('#datasetTab form button').contains('Save').click();
		cy.wait(7000);

		cy.waitDatasetTabLoading('datasetTab');
		cy.get('#datasetTab-button .pkpBadge').contains('2');
	});
	it('Check research data can be published in article acceptance', function () {
		cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submission.title);

		cy.get('ul.pkp_workflow_decisions:visible a:contains("Accept and Skip Review")', { timeout: 30000 }).click();
		cy.get('button:contains("Next:")').click();

		cy.get('#researchDataNotice').contains(/This submission contains deposited research data that is not yet public: https:\/\/doi\.org\/10\.[^\/]*\/.{3}\/.{6}/);
		cy.get('#researchDataNotice').contains('In case you choose to publish them, make sure they are suitable for publication in');
		cy.get('#researchDataPublishChoice').contains('Would you like to publish the research data?');
		
		cy.get('input[name="shouldPublishResearchData"][value="1"]').parent().contains("Yes");
		cy.get('input[name="shouldPublishResearchData"][value="0"]').parent().contains("No");
		cy.get('input[name="shouldPublishResearchData"][value="1"]').should('not.be.checked');
		cy.get('input[name="shouldPublishResearchData"][value="0"]').should('not.be.checked');
		
		cy.get('input[name="shouldPublishResearchData"][value="0"]').click();
		cy.get('button:contains("Record Editorial Decision")').click();
		cy.wait(1000);
		
		cy.get('#workflow-button').click();
		cy.get('li.pkp_workflow_editorial.initiated').within(() => {
			cy.contains('Copyediting');
		});
	});
});
