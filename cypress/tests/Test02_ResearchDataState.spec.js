import '../support/commands.js';

describe('Research data state', function () {
	let submission;

	before(function () {
		submission = {
			id: 0,
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

	it('Check reseach data state in submission wizard', function () {
		cy.login('eostrom', null, 'publicknowledge');

		cy.get('div#myQueue a:contains("New Submission")').click();

		if (Cypress.env('contextTitles').en_US != 'Public Knowledge Preprint Server') {
			cy.get('select[id="sectionId"],select[id="seriesId"]').select(submission.section);
		}

		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();

		cy.get('button.submitFormButton').click();
		cy.get('div:contains("It is required to inform the status of the research data.")');

		cy.get('input[id^="researchData-repoAvailable"]').click();
		cy.get('button.submitFormButton').click();
		cy.get('label[for^="researchDataUrl"].error').should('contain', 'This field is required');

		cy.get('input[name=researchDataUrl]').focus().clear().type('invalidUrl');
		cy.get('label[for^="researchDataUrl"].error').should('contain', 'Please enter a valid URL.');
		cy.get('input[name=researchDataUrl]').focus().clear()

		cy.get('input[id^="researchData-private"]').click();
		cy.get('button.submitFormButton').click();
		cy.get('label[for^="researchDataReason"].error').should('contain', 'This field is required');

		cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[name=researchDataReason]').focus().clear().type('Has sensitive data');

		cy.get('button.submitFormButton').click();

		cy.wait(500);
		cy.location('search').then(search => {
			submission.id = parseInt(search.split('=')[1], 10);
		});
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
		cy.get('select[id^="datasetSubject"').should('not.be.visible');

		cy.waitJQuery();
		cy.get('form[id=submitStep3Form] button:contains("Save and continue"):visible').click();

		cy.waitJQuery();
		cy.get('form[id=submitStep4Form]').find('button').contains('Finish Submission').click();
		cy.get('button.pkpModalConfirmButton').click();
		cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
	});

	it('Check reseach data state in research data tab', function () {
		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.allowAuthorToEditPublication('dbarnes', null, 'Elinor Ostrom');
		}

		cy.login('eostrom', null, 'publicknowledge');

		cy.visit('index.php/publicknowledge/authorDashboard/submission/' + submission.id);

		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();

		cy.get('.researchData__state').contains('The research data cannot be made publicly available, with the justification: Has sensitive data');

		cy.get('.researchData__header > .researchData__stateButton button:contains("Edit")').click();
		cy.get('input[name="researchDataState"][value="inManuscript"]').click();
		cy.get('.researchData__stateForm button:contains("Save")').click();
		cy.get('.researchData__state').contains('Research data is contained in the manuscript');
		cy.wait(1000);

		cy.get('.researchData__header button:contains("Edit")').click();
		cy.get('input[name="researchDataState"][value="repoAvailable"]').click();
		cy.get('input[name="researchDataUrl"]').type('https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM', { delay: 0 });
		cy.get('.researchData__stateForm button:contains("Save")').click();
		cy.get('.researchData__state').contains('Research data available at https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
		cy.get('.researchData__state a').should('have.attr', 'href', 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM');
		cy.wait(1000);

		cy.get('.researchData__header button:contains("Edit")').click();
		cy.get('input[name="researchDataState"][value="onDemand"]').click();
		cy.get('.researchData__stateForm button:contains("Save")').click();
		cy.get('.researchData__state').contains('Research data is available on demand.The condition is justified in the manuscript');
	});

	it('Check submission landing page displays research data state', function () {
		const media = Cypress.env('contextTitles').en_US === 'Public Knowledge Preprint Server' ? 'preprint' : 'article';

		cy.login('dbarnes');
		cy.visit('/index.php/publicknowledge/workflow/access/' + submission.id);

		if (Cypress.env('contextTitles').en_US !== 'Public Knowledge Preprint Server') {
			cy.sendToReview();
			cy.assignReviewer('Julie Janssen');
			cy.recordEditorialDecision('Accept Submission');
			cy.recordEditorialDecision('Send To Production');
			cy.get('li.ui-state-active a:contains("Production")');
			cy.publish('1', 'Vol. 1 No. 2 (2014)');
		} else {
			cy.get('#publication-button').click();
			cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton').click();
			cy.get('.pkp_modal_panel button:contains("Post")').click();
			cy.contains('This version has been posted and can not be edited.');
		}

		cy.get('.researchData__header button:contains("Edit")').should('not.exist');
		cy.get('.researchData__header button:contains("Upload research data")').should('not.exist');

		cy.visit(`/index.php/publicknowledge/${media}/view/${submission.id}`);
		cy.contains('Research data is available on demand.The condition is justified in the manuscript');
	});
});
