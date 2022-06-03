import '../support/commands';

var adminUser = Cypress.env('adminUser');
var adminPassword = Cypress.env('adminPassword');
var serverName = Cypress.env('serverName');
var currentYear = new Date().getFullYear();

describe('Deposit Draft Dataverse on Submission', function() {

    it('Dataverse Plugin Configuration', function() {
        cy.login(adminUser, adminPassword, serverName);
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
        cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]').check();
        cy.wait(2000);
        cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]').should('be.checked');
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin"] > .first_column > .show_extras').click();
        cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin-control-row"] > td > :nth-child(1)').click();
        cy.get('input[name="dataverseUrl"]').invoke('val', Cypress.env('dataverseURI'));
        cy.get('input[name="apiToken"]').invoke('val', Cypress.env('dataverseAPIToken'));
        cy.get('form[id="dataverseAuthForm"] button[name="submitFormButton"]').click();
        cy.get('div:contains("Your changes have been saved.")');
    });

    it('Create Submission', function() {
        cy.login(adminUser, adminPassword, serverName);
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
        cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]').check();
        cy.wait(2000);
        cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]').should('be.checked');
        cy.get('.app__nav a').contains('Submissions').click();

		cy.DataverseCreateSubmission({
            'submitterRole': 'Preprint Server manager',
            'title': 'The Rise of The Machine Empire',
            'abstract': 'An example abstract',
            'keywords': ['Modern History'],
            'files': [
                {
                    'galleyLabel': 'CSV',
                    'file': 'dummy.pdf',
                    'fileName': 'Data Table.pdf',
                    'fileTitle': 'Data Table',
                    'genre': 'Data Set',
                    'publishData': true,
                    'metadata': {
                        'sponsor': 'CAPES'
                    }
                },
                {
                    'galleyLabel': 'JPG',
                    'file': 'dummy.pdf',
                    'fileName': 'Amostra.pdf',
                    'fileTitle': 'Amostra',
                    'genre': 'Data Set',
                    'publishData': false,
                    'metadata': {
                        'sponsor': 'SciELO'
                    }
                }
            ],
            'additionalAuthors': [
                {
                    'givenName': 'Íris',
                    'familyName': 'Castanheiras',
                    'email': 'iris@lepidus.com.br',
                    'affiliation': 'Preprints da Lepidus',
                    'country': 'Argentina'
                }
            ]
		});
    });
});

describe('Publish Draft Dataverse on Submission Publish', function() {

    it('Publish Created Submission', function() {
        cy.login(adminUser, adminPassword, serverName);
        cy.get('#myQueue a:contains("View"):first').click();
        cy.wait(1000);
        cy.get('li > .pkpButton').click();
        cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.wait(2000);
        cy.get('.pkpPublication__versionPublished:contains("This version has been posted and can not be edited.")');
    });

    it('Goes to preprint view page', function() {
        cy.login(adminUser, adminPassword, serverName);
        cy.get('.pkpTabs__buttons > #archive-button').click();
        cy.wait(1000);
        cy.get('#archive a:contains("View"):first').click();
        cy.get('#publication-button').click();
        cy.get('.pkpHeader > .pkpHeader__actions > a:contains("View")').click();
        cy.waitJQuery();
    });

    it('Check Publication has Dataset Citation', function() {
        cy.get('.label').contains('Research data');
        cy.get('.value > p').contains('Íris Castanheiras, ' + currentYear + ', "The Rise of The Machine Empire"');
    });

    it('Checks "PDF" download button is hidden', function() {
        cy.get('.supplementary_galleys_links > li > a:contains("PDF")').should('not.exist');
    });

    it('Checks "JPG" download button is shown', function() {
        cy.get('.supplementary_galleys_links > li > a:contains("JPG")');
    });
});

describe('Hides button for deposited components in "latest preprints" listing', function() {
    it('Goes to index page', function() {
    cy.login(adminUser, adminPassword, serverName);
        cy.get('.app__header > a.app__contextTitle').click();
    });

    it('Checks "PDF" download button is hidden', function() {
        cy.get(
            '.obj_article_summary > .galleys_links > li > a:contains("PDF")'
        ).should('not.exist');
    });

    it('Checks "JPG" download button is shown', function() {
        cy.get('.obj_article_summary > .galleys_links > li > a:contains("JPG")');
    });
});