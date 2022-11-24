import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');
const serverName = Cypress.env('serverName');
const dataverseServerName = Cypress.env('dataverseServerName');
const currentYear = new Date().getFullYear();

let submissionData = {
	submitterRole: 'Preprint Server manager',
	title: 'The History of Coffee',
	abstract: 'A descriptive text',
	keywords: ['Documentary'],
	files: [
		{
			galleyLabel: 'CSV',
			file: 'dummy.pdf',
			fileName: 'Data Table.pdf'
		}
	],
	additionalAuthors: [
		{
			givenName: 'Íris',
			familyName: 'Castanheiras',
			email: 'iris@lepidus.com.br',
			affiliation: 'Preprints da Lepidus',
			country: 'Argentina'
		}
	]
};

describe('Deposit Draft Dataset', function() {
	it('Dataverse Plugin Configuration', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('.app__nav a')
			.contains('Website')
			.click();
		cy.get('button[id="plugins-button"]').click();
		cy.get(
			'#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]'
		).check();
		cy.wait(2000);
		cy.get(
			'#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]'
		).should('be.checked');
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras'
		).click();
		cy.get(
			'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)'
		).click();
		cy.get('input[name="dataverseUrl"]').invoke(
			'val',
			Cypress.env('dataverseURI')
		);
		cy.get('input[name="apiToken"]').invoke(
			'val',
			Cypress.env('dataverseAPIToken')
		);
		cy.get(
			'form[id="dataverseAuthForm"] button[name="submitFormButton"]'
		).click();
		cy.get('div:contains("Your changes have been saved.")');
	});

	it('Create Submission', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('.app__nav a')
			.contains('Website')
			.click();
		cy.get('button[id="plugins-button"]').click();
		cy.get(
			'#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]'
		).check();
		cy.wait(2000);
		cy.get(
			'#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]'
		).should('be.checked');
		cy.get('.app__nav a')
			.contains('Submissions')
			.click();

		cy.DataverseCreateSubmission(submissionData);
	});
});

describe('Edit Dataset Metadata Draft', function() {
	it('Check dataset metadata form exists', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
	});

	it('Check dataset metadata edit is enabled when preprint is unpublished', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
		cy.get('.pkpPublication__status span').contains('Unposted');
		cy.get(
			'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
		).should('not.be.disabled');
	});

	it('Change dataset metadata if preprint is unpublished', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
		cy.get('.pkpPublication__status span').contains('Unposted');
		cy.get(
			'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
		).should('not.be.disabled');
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
		cy.wait(3000);
	});

	it('Check dataset metadata has been changed', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
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

	it('Removes keyword metadata from dataset', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
		cy.get('.pkpPublication__status span').contains('Unposted');
		cy.get(
			'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
		).should('not.be.disabled');
		cy.get('#datasetMetadata-datasetKeywords-control').clear();
		cy.get(
			'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
		).click();
		cy.wait(3000);
	});

	it('Check keyword metadata has empty', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
		cy.get('#datasetMetadata-datasetKeywords-selected').should(
			'not.include.text',
			'Modern History'
		);
	});

	it('Adds keyword metadata to dataset', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
		cy.get('.pkpPublication__status span').contains('Unposted');
		cy.get(
			'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
		).should('not.be.disabled');
		cy.get('#datasetMetadata-datasetKeywords-control').type('Documentary', {
			delay: 0
		});
		cy.wait(500);
		cy.get('#datasetMetadata-datasetKeywords-control').type('{enter}', {
			delay: 0
		});
		cy.get(
			'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
		).click();
		cy.wait(3000);
	});

	it('Check keyword metadata has value', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
		cy.get('#datasetMetadata-datasetKeywords-selected').contains('Documentary');
	});
});

describe('Edit Draft Dataset Files', function() {
	it('Check dataset files list exists', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('button[aria-controls="dataset_files"]').click();
		cy.get('#datasetFiles').should('be.visible');
		cy.get('#datasetFiles .listPanel__items').contains('Data Table.pdf');
	});

	it('Adds file to dataset', function() {
		cy.login(adminUser, adminPassword);
		cy.visit(
			'index.php/' + serverName + '/workflow/access/' + submissionData.id
		);
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('button[aria-controls="dataset_files"]').click();
		cy.get('button')
			.contains('Upload research data')
			.click();
		cy.fixture('dummy.pdf', 'base64').then(fileContent => {
			cy.get('#datasetFileForm-datasetFile-hiddenFileId').upload({
				fileContent,
				fileName: 'riseOfEmpireMachine.pdf',
				mimeType: 'application/pdf',
				encoding: 'base64'
			});
		});
		cy.get('input[name="termsOfUse"').check();
		cy.get('[data-modal="datasetFileModal"] button:contains("Save")').click();
		cy.get('#datasetFiles .listPanel__items').contains(
			'riseOfEmpireMachine.pdf'
		);
	});
});

describe('Check dataset data edit is disabled', function() {
	it('Check dataset metadata edit is disabled when preprint is published', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.visit(
			'index.php/' + serverName + '/workflow/access/' + submissionData.id
		);
		cy.get('button[aria-controls="publication"]').click();
		cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
			'be.visible'
		);
		cy.get('.pkpPublication__status span').contains('Unposted');
		cy.get(
			'.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton'
		).click();
		cy.get('.pkp_modal_panel button:contains("Post")').click();
		cy.wait(2000);
		cy.get(
			'.pkpPublication__versionPublished:contains("This version has been posted and can not be edited.")'
		);
		cy.get('.pkpPublication__status span').contains('Posted');
		cy.get(
			'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
		).should('be.disabled');
	});
});
