import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');
const serverPath = Cypress.env('serverPath') || 'publicknowledge';

describe('Edit Dataset Metadata after deposit', function() {
	it('Dataverse Plugin Configuration', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.configureDataversePlugin();
	});

	it('Change dataset metadata', function() {
		cy.register({
			username: 'iriscastanheiras',
			givenName: 'Íris',
			familyName: 'Castanheiras',
			affiliation: 'Lepidus',
			country: 'Argentina'
		});

		cy.DataverseCreateSubmission({
			title: 'The History of Coffee',
			abstract: 'A descriptive text',
			keywords: ['Documentary'],
			files: [
				{
					galleyLabel: 'CSV',
					file: 'dummy.pdf',
					fileName: 'Data Table.pdf'
				}
			]
		});

		cy.get('a')
			.contains('Review this submission')
			.click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('input[id^="datasetMetadata-datasetTitle-control"').clear();
		cy.get(
			'input[id^="datasetMetadata-datasetTitle-control"'
		).type('The Rise of the Empire Machine', {delay: 0});
		cy.get('div[id^="datasetMetadata-datasetDescription-control"').clear();
		cy.get(
			'div[id^="datasetMetadata-datasetDescription-control"'
		).type('An example abstract', {delay: 0});
		cy.get('#datasetMetadata-datasetKeywords-control').type('Modern History', {
			delay: 0
		});
		cy.wait(500);
		cy.get('#datasetMetadata-datasetKeywords-control').type('{enter}', {
			delay: 0
		});
		cy.get(
			'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
		).click();
		cy.waitJQuery();
	});

	it('Check Dataset metadata has changed', function() {
		cy.login('iriscastanheiras');
		cy.visit('index.php/' + serverPath + '/submissions');
		cy.contains('View Castanheiras').click({force: true});
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('input[id^="datasetMetadata-datasetTitle-control"').should(
			'have.value',
			'The Rise of the Empire Machine'
		);
		cy.get(
			'div[id^="datasetMetadata-datasetDescription-control"] > p'
		).contains('An example abstract');
		cy.get('#datasetMetadata-datasetKeywords-selected').contains(
			'Modern History'
		);
	});

	it('Check update event was registered in activity log', function() {
		cy.findSubmissionAsEditor(adminUser, adminPassword, 'Castanheiras', serverPath);
		cy.get('button[aria-controls="publication"]').click();
		cy.contains('Activity Log').click();
		cy.get('#submissionHistoryGridContainer tbody tr:first td').should(($cell) => {
			expect($cell[1]).to.contain('Íris Castanheiras');
			expect($cell[2]).to.contain('Research data metadata updated');
		});
	});

	it('Delete draft dataset', function() {
		cy.login('iriscastanheiras');
		cy.visit('index.php/' + serverPath + '/submissions');
		cy.contains('View Castanheiras').click({force: true});
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('button')
			.contains('Delete research data')
			.click();
		cy.get('[data-modal="delete"] button')
			.contains('Yes')
			.click();
		cy.contains('No research data transferred');
	});

	it('Check delete event was registered in activity log', function() {
		cy.findSubmissionAsEditor(adminUser, adminPassword, 'Castanheiras', serverPath);
		cy.get('button[aria-controls="publication"]').click();
		cy.contains('Activity Log').click();
		cy.get('#submissionHistoryGridContainer tbody tr:first td').should(($cell) => {
			expect($cell[1]).to.contain('Íris Castanheiras');
			expect($cell[2]).to.contain('Research data deleted');
		});
	});
});
