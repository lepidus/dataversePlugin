import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');
const serverPath = Cypress.env('serverPath') || 'publicknowledge';

describe('Edit Dataset Metadata after deposit', function (){
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

    it('Change dataset metadata', function() {
		cy.register({
			'username': 'iriscastanheiras',
			'givenName': 'Íris',
			'familyName': 'Castanheiras',
			'affiliation': 'Lepidus',
			'country': 'Argentina',
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

        cy.get('a').contains('Review this submission').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('input[id^="datasetMetadata-datasetTitle-control"').clear();
		cy.get('input[id^="datasetMetadata-datasetTitle-control"').type('The Rise of the Empire Machine', {delay: 0});
		cy.get('div[id^="datasetMetadata-datasetDescription-control"').clear();
		cy.get('div[id^="datasetMetadata-datasetDescription-control"').type('An example abstract', {delay: 0});
		cy.get('#datasetMetadata-datasetKeywords-control').type('Modern History', {delay: 0});
		cy.wait(500);
		cy.get('#datasetMetadata-datasetKeywords-control').type('{enter}', {delay: 0});
		cy.get('div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]').click();
        cy.waitJQuery();
	});

    it('Check Dataset metadata has changed', function() {
        cy.login('iriscastanheiras');
        cy.visit('index.php/' + serverPath + '/submissions');
        cy.contains('View Castanheiras').click({force: true});
        cy.get('button[aria-controls="datasetTab"]').click();
		cy.get('input[id^="datasetMetadata-datasetTitle-control"').should('have.value','The Rise of the Empire Machine');
		cy.get('div[id^="datasetMetadata-datasetDescription-control"] > p').contains('An example abstract');
		cy.get('#datasetMetadata-datasetKeywords-selected').contains('Modern History');
    });
});