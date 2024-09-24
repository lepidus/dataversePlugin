import '../support/commands.js';

describe('Research data state', function () {
	let submission;

	before(function () {
		submission = {
			section: 'Articles',
			title: 'Socio-Environmental Education: Promoting Sustainability and Global Citizenship',
			abstract: 'Socio-environmental education promotes sustainable and equitable development by raising awareness and promoting responsible practices. It fosters engaged and conscious global citizens.',
			keywords: [
				'socio-environmental education',
				'sustainable development',
				'equitable development'
			],
		}
	});

	it('Check data statement in submission wizard', function () {
		cy.login('eostrom', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();

		if (Cypress.env('contextTitles').en_US == 'Journal of Public Knowledge') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
		}
		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();

		cy.get('input[id^="dataStatementReason-en_US-"]').should('not.be.visible');
		cy.get('ul[id^="dataStatementUrls"]').should('not.be.visible');

		cy.get('button.submitFormButton').click();
		cy.get('div:contains("It is required to inform the declaration of the data statement.")');

		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id^="dataStatementTypes"][value=2]').click();
		cy.get('ul[id^="dataStatementUrls"]').should('be.visible');
		cy.get('button.submitFormButton').click();
		cy.get('div:contains("It is required to inform the URLs to the data in repositories.")');

		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id^="dataStatementTypes"][value=2]').click();
		cy.get('ul[id^="dataStatementUrls"]').then((node) => {
			node.tagit('createTag', 'Example text');
		});
		cy.get('button.submitFormButton').click();
		cy.contains('You must only enter the URLs to the data. Other textual information is not accepted.');

		cy.get('input[id^="dataStatementTypes"][value=2]').click();
		cy.get('ul[id^="dataStatementUrls"]').then((node) => {
			node.tagit('createTag', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
		});

		cy.get('input[id^="dataStatementTypes"][value=5]').click();
		cy.get('input[id^="dataStatementReason-en_US-"]').should('be.visible');
		cy.get('button.submitFormButton').click();
		cy.get('label[for^="dataStatementReason"].error').should('contain', 'This field is required');

		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id^="dataStatementReason-en_US-"]').focus().clear().type('Has sensitive data');

		cy.get('button.submitFormButton').click();

		cy.wait(500);
		cy.get('#submitStep2Form button.submitFormButton').click();

		cy.get('input[id^="title-en_US-"').type(submission.title, { delay: 0 });
		cy.get('label').contains('Title').click();
		cy.get('textarea[id^="abstract-en_US-"').then((node) => {
			cy.setTinyMceContent(node.attr('id'), submission.abstract);
		});
		cy.get('ul[id^="en_US-keywords-"]').then((node) => {
			for(let keyword of submission.keywords) {
				node.tagit('createTag', keyword);
			}
		});
		cy.get('select[id^="datasetSubject"').should('not.be.visible');

		cy.waitJQuery();
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.waitJQuery();
		cy.get('form[id=submitStep4Form] button:contains("Finish Submission")').click();
		cy.get('button.pkpModalConfirmButton').click();
		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
	});

	it('Check data statement edit in data statement tab', function () {
		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.allowAuthorToEditPublication('dbarnes', null, 'Elinor Ostrom');
		}

		cy.login('eostrom', null, 'publicknowledge');
		cy.get('.pkpButton:visible:contains("View")').first().click();

		cy.get('#publication-button').click();
		cy.get('#dataStatement-button').click();

		cy.get('input[name="dataStatementTypes"][value="2"]').should('be.checked');
		cy.get('input[name="dataStatementTypes"][value="5"]').should('be.checked');

		cy.get('#dataStatement-dataStatementUrls-selected span').contains('https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
		cy.get('input[id="dataStatement-dataStatementReason-control-en_US"').should('have.value', 'Has sensitive data');

		cy.get('input[name="dataStatementTypes"][value="2"]').click();
		cy.get('input[id="dataStatement-dataStatementUrls-control"').should('not.be.visible');
		cy.get('input[name="dataStatementTypes"][value="5"]').click();
		cy.get('input[id="dataStatement-dataStatementReason-control-en_US"').should('not.be.visible');

		cy.get('input[name="dataStatementTypes"]').check({ multiple: true });
		cy.get('#dataStatement button').contains('Français (Canada)').click();
		cy.get('input[id="dataStatement-dataStatementReason-control-fr_CA"').clear();
		cy.get('input[id="dataStatement-dataStatementReason-control-fr_CA"').type('Contient des données sensibles');
		cy.get('#dataStatement button').contains('Save').click();
		cy.get('#dataStatement [role="status"]').contains('Saved');
	});

	it('Check submission landing page displays data statement state', function () {
		const currentYear = new Date().getFullYear();

		cy.login('dbarnes', null, 'publicknowledge');
		cy.get('#active-button').click();
		cy.get('.pkpButton:visible:contains("View")').first().click();

		cy.waitDataStatementTabLoading();
		cy.wait(2000);
		
		cy.get('#datasetTab-button').click();
		cy.get('button:contains("Upload research data")').click();
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
		cy.get('select[id^="datasetMetadata-datasetSubject-control"').select('Other');
		cy.get('select[id^="datasetMetadata-datasetLicense-control"').select('CC BY 4.0');
		cy.get('#datasetTab form button:contains("Save")').click();
		cy.wait(500);
		cy.get('#datasetTab [role="status"] span:contains("Saved")');
		cy.wait(1000);

		cy.waitDatasetTabLoading('workflow');

		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.sendToReview();
			cy.waitDatasetTabLoading('workflow');
			cy.assignReviewer('Julie Janssen');
			cy.recordEditorialDecision('Accept Submission');
			cy.recordEditorialDecision('Send To Production');
			cy.get('li.ui-state-active a:contains("Production")');
			cy.get('#publication-button').click();
			cy.get('div#publication button:contains("Schedule For Publication")').click();
			cy.get('select[id="assignToIssue-issueId-control"]').select('1');
			cy.get('div[id^="assign-"] button:contains("Save")').click();
			cy.get('div[id^="assign-"] [role="status"]').contains('Saved');
			cy.reload();
			cy.get('div#publication button:contains("Schedule For Publication")').click();
			cy.get('input[name="shouldPublishResearchData"][value="1"]').click();
			cy.get('div.pkpWorkflow__publishModal button:contains("Publish")').click();
		} else {
			cy.get('#publication-button').click();
			cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton').click();
			cy.get('input[name="shouldPublishResearchData"][value="1"]').click();
			cy.get('.pkp_modal_panel button:contains("Post")').click();
			cy.contains('This version has been posted and can not be edited.');
		}

		cy.get('#dataStatement button:contains("Save")').should('be.disabled');

		cy.get('.pkpHeader__actions a:contains("View")').click();

		cy.get('.label:contains("Data statement")');
		cy.contains('The research data is contained in the manuscript');
		cy.contains('The research data is available in one or more data repository(ies)').next().contains('https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
		cy.contains('The research data is available on demand, condition justified in the manuscript');
		cy.contains('The research data cannot be made publicly available').next().contains('Has sensitive data');

		cy.get('.label:contains("Research data")');
		cy.get('.data_citation').contains('Ostrom, Elinor, ' + currentYear + ', "Replication data for: ' + submission.title + '"');
		cy.get('.data_citation').contains(/https:\/\/doi\.org\/10\.[^\/]*\/FK2\//);
	});
});
