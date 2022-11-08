import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');
const serverName = Cypress.env('serverName');
const currentYear = new Date().getFullYear();

describe('Deposit Draft Dataverse on Submission', function() {
	it('Dataverse Plugin Configuration', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a').contains(adminUser).click();
		cy.get('a').contains('Dashboard').click();
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
		cy.get('a').contains(adminUser).click();
		cy.get('a').contains('Dashboard').click();
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

		cy.DataverseCreateSubmission({
			submitterRole: 'Preprint Server manager',
			title: 'The Rise of The Machine Empire',
			abstract: 'An example abstract',
			keywords: ['Modern History'],
			files: [
				{
					galleyLabel: 'CSV',
					file: 'dummy.pdf',
					fileName: 'Data Table.pdf'
				},
				{
					galleyLabel: 'JPG',
					file: 'dummy.pdf',
					fileName: 'Amostra.pdf'
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
		});
	});
});

describe('Publish Draft Dataverse on Submission Publish', function() {
	it('Publish Created Submission', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a').contains(adminUser).click();
		cy.get('a').contains('Dashboard').click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.wait(1000);
		cy.get('li > .pkpButton').click();
		cy.get('#datasetTab-button').click();
		cy.get('.label').contains('Research data');
		cy.get('#data_citation > .value > p').contains(
			'Castanheiras, Í. (' +
				currentYear +
				'). The Rise of The Machine Empire. ' +
				serverName +
				','
		);
		cy.get('.value > p > a:contains("https://doi.org/10.70122/FK2/")');
		cy.get('.value > p').contains(', Demo Dataverse, Vundefined');
		cy.get(
			'.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton'
		).click();
		cy.get('.pkp_modal_panel button:contains("Post")').click();
		cy.wait(2000);
		cy.get(
			'.pkpPublication__versionPublished:contains("This version has been posted and can not be edited.")'
		);
	});
	it('Goes to preprint view page', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a').contains(adminUser).click();
		cy.get('a').contains('Dashboard').click();
		cy.get('.pkpTabs__buttons > #archive-button').click();
		cy.wait(1000);
		cy.get('#archive a:contains("View"):first').click();
		cy.get('#publication-button').click();
		cy.get('.pkpHeader > .pkpHeader__actions > a:contains("View")').click();
		cy.waitJQuery();
	});

	it('Check Publication has Dataset Citation', function() {
		cy.get('.label').contains('Research data');
		cy.get('.value > p').contains(
			'Castanheiras, Í. (' +
				currentYear +
				'). The Rise of The Machine Empire. ' +
				serverName +
				','
		);
		cy.get('.value > p > a:contains("https://doi.org/10.70122/FK2/")');
		cy.get('.value > p').contains(', Demo Dataverse, V1');
	});
});

describe('Create Submission without research data files', function() {
	it('Create Submission', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a').contains(adminUser).click();
		cy.get('a').contains('Dashboard').click();
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

		cy.DataverseCreateSubmission({
			submitterRole: 'Preprint Server manager',
			title: 'The Rise of The Machine Empire (no files)',
			abstract: 'An example abstract',
			keywords: ['Modern History'],
			files: [],
			additionalAuthors: [
				{
					givenName: 'Íris',
					familyName: 'Castanheiras',
					email: 'iris@lepidus.com.br',
					affiliation: 'Preprints da Lepidus',
					country: 'Argentina'
				}
			]
		});
	});

	it('Verify "Research Data" tab is not visible', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a').contains(adminUser).click();
		cy.get('a').contains('Dashboard').click();
		cy.get('#myQueue a:contains("View"):first').click();
		cy.wait(1000);
		cy.get('li > .pkpButton').click();
		cy.get('#datasetTab-button').should('not.visible');
	});
});