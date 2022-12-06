const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');
const serverPath = Cypress.env('serverPath') || 'publicknowledge';

var submissionId = null;

function activeDataversePlugin() {
	cy.login(adminUser, adminPassword);
	cy.get('a:contains(' + adminUser + '):visible').click();
	cy.get('a:contains("Dashboard"):visible').click();
	cy.get('.app__nav a')
		.contains('Website')
		.click();
	cy.get('button[id="plugins-button"]').click();
	cy.get('body').then($body => {
		if (
			!(
				$body.find(
					'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > :nth-child(3) > :nth-child(1) > :checked'
				).length > 0
			)
		) {
			cy.get(
				'#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) >'
			).click();
			cy.get(
				'div:contains(\'The plugin "Dataverse Plugin" has been enabled.\')'
			);
		}
	});
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
	cy.get('input[name^="termsOfUse"]').invoke(
		'val',
		Cypress.env('dataverseTermsOfUse')
	);
	cy.get(
		'form[id="dataverseAuthForm"] button[name="submitFormButton"]'
	).click();
	cy.get("div:contains('Your changes have been saved.')");
}

describe('Defines a subject to submission dataset', function() {
	it('Dataverse Plugin Configuration', function() {
		activeDataversePlugin();
	});

	it('Check subject field is visible when submission has research data', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a')
			.contains(adminUser)
			.click();
		cy.get('a')
			.contains('Dashboard')
			.click();
		cy.get(
			'a:contains("Make a New Submission"), div#myQueue a:contains("New Submission")'
		).click();

		cy.get('input[id^="checklist-"]').click({multiple: true});
		cy.get('input[id=privacyConsent]').click();
		cy.get('input[name=userGroupId]')
			.parent()
			.contains('Preprint Server manager')
			.click();
		cy.get('button.submitFormButton').click();

		cy.get('button:contains("Upload research data")').click();
		cy.get('[data-modal="datasetModal"]').then($datasetModal => {
			cy.fixture('dummy.pdf', 'base64').then(fileContent => {
				cy.get('input[type=file]').upload({
					fileContent,
					fileName: 'Data Table.pdf',
					mimeType: 'application/pdf',
					encoding: 'base64'
				});
			});
			cy.get('input[name="termsOfUse"').check();
			cy.get('[data-modal="datasetModal"] button:contains("Save")').click();
		});

		cy.location('search').then(search => {
			submissionId = parseInt(search.split('=')[1], 10);
		});

		cy.waitJQuery();
		cy.get('button.submitFormButton').click();

		cy.get('select[id^="datasetSubject"').should('be.visible');
	});

	it('Check subject field is not visible when submission not has research data', function() {
		cy.login(adminUser, adminPassword);
		cy.visit(
			'index.php/' +
				serverPath +
				'/submission/wizard/2?submissionId=' +
				submissionId +
				'#step-2'
		);
		cy.get('a[name="step-2"]').click();
		cy.get(
			'.listPanel__item:contains(Data Table.pdf) .listPanel__itemActions button'
		).click();
		cy.get('[data-modal="delete"] button:contains(Yes)').click();
		cy.waitJQuery();
		cy.get('button.submitFormButton').click();

		cy.get('select[id^="datasetSubject"').should('not.be.visible');
	});
});
