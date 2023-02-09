const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');

let moderator = {
	'username': 'hermesf',
	'password': 'hermesfhermesf',
	'email': 'hermesf@mailinator.com',
	'givenName': 'Hermes',
	'familyName': 'Fernandes',
	'country': 'Brazil',
	'affiliation': 'Dataverse Project',
	'roles': ['Moderator']
};

describe('Tests setup', function() {
	it('Creates a context', function() {
		cy.login(adminUser, adminPassword);
		cy.get('a').contains(adminUser).click();
		cy.get('a').contains('Dashboard').click();
		cy.get('.app__nav a').contains('Administration').click();
		cy.get('a').contains('Hosted Servers').click();

		cy.get('div[id=contextGridContainer]').find('a').contains('Create').click();

		cy.wait(2000);
		cy.get('input[name="name-en_US"]').type('Dataverse Preprints', {delay: 0});
		cy.get('input[name=acronym-en_US]').type('DVNP', {delay: 0});
		cy.get('span').contains('Enable this preprint server').siblings('input').check();
		cy.get('input[name="supportedLocales"]').click({multiple: true});
		cy.get('input[name="primaryLocale"][value="en_US').check();
		cy.get('input[name=urlPath]').type('dvnpreprints', {delay: 0});

		cy.get('button').contains('Save').click();

		cy.contains('Settings Wizard', {timeout: 30000});

		cy.login(adminUser, adminPassword, 'dvnpreprints');
		cy.get('.app__nav a').contains('Administration').click();
		cy.get('a').contains('Hosted Servers').click();
		cy.get('tr:contains(Dataverse Preprints) a[class=show_extras]').click();
		cy.get('a:contains("Settings wizard"):visible').click();

		cy.get('button[id="languages-button"]').click();
		cy.get('input[id*="submissionLocale"]').check({multiple: true});
		cy.get('input[id*="formLocale"]').check({multiple: true});
		cy.contains('Locale settings saved.');
	});

	it('Creates a moderator user', function() {
		cy.login(adminUser, adminPassword, 'dvnpreprints');
		cy.get('a:contains("Users & Roles")').click();

		cy.createUser(moderator);
		cy.logout();

		cy.login(moderator.username);
		cy.resetPassword(moderator.username, moderator.password);
		cy.logout();
	});

	it('Dataverse Plugin Configuration', function () {
		cy.login(adminUser, adminPassword);
		cy.get('a:contains(' + adminUser + '):visible').click();
		cy.get('a:contains("Dashboard"):visible').click();
		cy.configureDataversePlugin();
	});
});