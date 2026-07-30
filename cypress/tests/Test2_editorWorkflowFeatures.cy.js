import '../support/commands.js';

function assertAdditionalInstructionsDisplay() {
	cy.contains('1. Submit under "Research Data" any files that have been collected');
	cy.contains('2. It is mandatory to include a file named "Readme"/"Leiame"/"Leame"');
	cy.contains('For additional guidance on creating the file, consult the suggested references below');
	cy.contains('3. The files deposited in "Research Data" will form a dataset');
}

describe('Dataverse Plugin - Editor workflow features', function () {
	const submissionData = {
		title: 'Editor workflow with controlled research data',
		abstract: 'A submission prepared to verify the editor research data workflow.',
		keywords: ['editor workflow'],
		dataStatementTypes: [3],
		articleFileName: 'editor-workflow-article.pdf',
		datasetLanguage: 'English',
		datasetSubject: 'Earth and Environmental Sciences',
		datasetLicense: 'CC BY 4.0',
		datasetRelationType: 'IsCitedBy',
	};
	let submissionId;

	before(function () {
		cy.startControlledDataverse('doi:10.5072/FK2/WORKFLOWEDITOR').then((controlledDataverseUrl) => {
			cy.login('dbarnes', null, 'publicknowledge');
			cy.ensureDataversePluginEnabled();
			cy.configureDataverse({
				url: controlledDataverseUrl,
				apiToken: 'valid-token',
				termsOfUse: 'https://example.test/terms',
				datasetPublish: 2,
			});
			cy.logout();
		});

		cy.depositDataverseSubmissionWithApi('eostrom', submissionData, [{
			fixture: 'example.json',
			fileName: 'example.json',
			mimeType: 'application/json',
			encoding: 'utf8',
		}, {
			fixture: '../../plugins/generic/dataverse/cypress/fixtures/README.pdf',
			fileName: 'README.pdf',
			mimeType: 'application/pdf',
			encoding: 'base64',
		}]);
		cy.get('@submissionId').then((id) => {
			submissionId = id;
		});
	});

	after(function () {
		cy.restoreExternalDataverseConfiguration();
		cy.stopControlledDataverse();
	});

	it('Editor can delete research data in workflow', function () {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit(`/index.php/publicknowledge/workflow/index/${submissionId}/1`);

		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();

		cy.contains('.datasetLabel', 'Draft');
		cy.contains('.datasetLabel', 'Unpublished');

		cy.get('#deleteDatasetButton').click();
		cy.contains('Send an email notification to the dataset contact');
		cy.contains('Do not send an email notification');
		cy.getTinyMceContent('deleteDataset-deleteMessage-control')
			.should('include', 'The research data from the manuscript submission "' + submissionData.title + '" has been removed');
		cy.get('.modal__panel button:contains("Delete and send email")').click();
		cy.contains('No research data transferred.', {timeout: 30000});
		assertAdditionalInstructionsDisplay();

		cy.get('#dataStatement-button').click();
		cy.get('input[name="researchDataSubmitted"]').should('not.be.checked');
	});

	it('Editor can upload research data in workflow', function () {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit(`/index.php/publicknowledge/workflow/index/${submissionId}/1`);

		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();

		cy.contains('button', 'Upload research data').click();
		cy.addDraftDatasetFile({
			fixture: 'example.json',
			fileName: 'example.json',
			mimeType: 'application/json',
			encoding: 'utf8',
		});
		cy.addDraftDatasetFile({
			fixture: '../../plugins/generic/dataverse/cypress/fixtures/README.pdf',
			fileName: 'README.pdf',
			mimeType: 'application/pdf',
			encoding: 'base64',
		});

		cy.get('#datasetMetadata-datasetLanguage-control').select('English');
		cy.get('#datasetMetadata-datasetSubject-control').select('Earth and Environmental Sciences');
		cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
		cy.get('#datasetMetadata-datasetRelationType-control').select('Is Cited By');
		cy.get('button:visible:contains("Save")').click();
		cy.contains('h1', 'Research data', {timeout: 30000});
	});

	it('Editor can keep the dataset private when publishing the submission', function () {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit(`/index.php/publicknowledge/workflow/index/${submissionId}/1`);

		cy.get('#workflow-button').click();
		cy.clickDecision('Send for Review');
		cy.contains('button', 'Skip this email').click();
		cy.contains('button', 'Continue').click();
		cy.contains('button', 'Record Decision').click();
		cy.get('a.pkpButton').contains('View Submission').click();
		cy.assignReviewer('Julie Janssen');

		cy.clickDecision('Accept Submission');
		cy.recordDecisionAcceptSubmission(['Elinor Ostrom'], [], []);

		cy.clickDecision('Send To Production');
		cy.recordDecisionSendToProduction(['Elinor Ostrom'], []);
		cy.isActiveStageTab('Production');

		cy.get('#publication-button').click();
		cy.get('div#publication button:contains("Schedule For Publication")').click();
		cy.get('select[id="assignToIssue-issueId-control"]').select('1');
		cy.get('div[id^="assign-"] button:contains("Save")').click();
		cy.get('div[id^="assign-"] [role="status"]').contains('Saved');
		cy.reload();
		cy.get('div#publication button:contains("Schedule For Publication")').click();

		cy.contains('Would you like to publish the research data?');
		cy.get('input[name="shouldPublishResearchData"][value="1"]').should('not.be.checked');
		cy.get('input[name="shouldPublishResearchData"][value="0"]').should('not.be.checked');
		cy.get('input[name="shouldPublishResearchData"][value="0"]').click();
		cy.get('.pkpWorkflow__publishModal button:contains("Publish")').click();
		cy.get('.pkpPublication__statusPublished', {timeout: 30000}).should('have.text', 'Published');
	});

	it('Editor publishes the dataset after publishing the submission', function () {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit(`/index.php/publicknowledge/workflow/index/${submissionId}/1`);

		cy.get('#publication-button').click();
		cy.get('#datasetTab-button').click();
		cy.contains('button', 'Publish research data').click();

		const publishMsg = 'Do you really want to publish the research data related to this submission? This action cannot be undone.'
			+ 'Before proceeding, make sure they are suitable for publication in ';
		cy.get('div[data-modal="publishDataset"]').contains(publishMsg);
		cy.intercept('POST', '**/api/v1/datasets/*/publish').as('publishDataset');
		cy.get('div[data-modal="publishDataset"] button:contains("Yes")').click();
		cy.wait('@publishDataset').then((interception) => {
			expect(interception.request.headers['x-http-method-override']).to.eq('PUT');
			expect(interception.response.statusCode).to.eq(200);
		});

		cy.contains('Publish research data').should('not.exist');
		cy.get('button:contains("Delete")').should('be.disabled');
		cy.get('button:contains("Add research data")').should('be.disabled');
		cy.get('#dataset_metadata button:contains("Save")').should('be.disabled');
		cy.contains('.datasetLabel', 'Draft').should('not.exist');
		cy.contains('.datasetLabel', 'Unpublished').should('not.exist');
	});

	it('Publishing a new submission version does not republish the dataset', function () {
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit(`/index.php/publicknowledge/workflow/index/${submissionId}/1`);

		cy.get('#publication-button').click();
		cy.contains('button', 'Create New Version').click();
		cy.get('.modal__panel button:contains("Yes")').click();
		cy.get('.pkpPublication__version:contains("2")');
		cy.contains('button', 'Publish').click();
		cy.contains('Would you like to publish the research data?').should('not.exist');
		cy.get('.pkpWorkflow__publishModal button:contains("Publish")').click();
		cy.get('.pkpPublication__statusPublished', {timeout: 30000}).should('have.text', 'Published');
		cy.get('#datasetTab-button').click();
		cy.contains('Controlled Dataverse, V1');
	});
});
