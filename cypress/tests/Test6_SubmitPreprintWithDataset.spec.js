import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');
const serverName = Cypress.env('serverName');
const dataverseServerName = Cypress.env('dataverseServerName');
const serverPath = Cypress.env('serverPath') || 'publicknowledge';
const currentYear = new Date().getFullYear();

const submissionData = {
    submitterRole: 'Preprint Server manager',
    title: 'The Rise of The Machine Empire',
    abstract: 'An example abstract',
    keywords: ['Modern History'],
    files: [
        {
            file: 'dummy.pdf',
            fileName: 'Data Table.pdf',
        },
        {
            file: 'dummy.pdf',
            fileName: 'Amostra.pdf',
        },
    ],
    additionalAuthors: [
        {
            givenName: 'Íris',
            familyName: 'Castanheiras',
            email: 'iris@lepidus.com.br',
            affiliation: 'Preprints da Lepidus',
            country: 'Argentina',
        },
    ],
};

const submissionDataNoFiles = {
	state: 'inManuscript',
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
            country: 'Argentina',
        },
    ],
};

describe('Deposit Draft Dataverse on Submission', function() {
    it('Dataverse Plugin Configuration', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a:contains(' + adminUser + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.configureDataversePlugin();
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

    it('Check deposit event was registered in activity log', function() {
        cy.login(adminUser, adminPassword);
        cy.contains(adminUser).click();
        cy.contains('Dashboard').click();
        cy.get('#myQueue a:contains("View"):first').click();
        cy.get('#publication-button').click();
        cy.contains('Activity Log').click();
        cy.get('#submissionHistoryGridContainer').contains(
            /Research data deposited: doi:10.{6}\/.{3}\/.{6}/
        );
    });
});

describe('Publish research data when submission is published', function() {
    it('Check research data notice is displayed', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a')
            .contains(adminUser)
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.get('#myQueue a:contains("View"):first').click();
        cy.get('li > .pkpButton').click();
        cy.get(
            '.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton'
        ).click();

        cy.get('div[id^=publish')
            .invoke('text')
            .should(
                'match',
                /This preprint has deposited research data: https:\/\/doi\.org\/10\.[^\/]*\/.{3}\/.{6}/
            )
            .and(
                'contain',
                'When posting this preprint, the data will also be published in Dataverse.'
            )
            .and(
                'contain',
                'Please make sure the research data is suitable for publication in ' +
                    dataverseServerName
            );
    });

    it('Publish Created Submission', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a')
            .contains(adminUser)
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.get('#myQueue a:contains("View"):first').click();
        cy.get('li > .pkpButton').click();
        cy.get('#datasetTab-button').click();
        cy.get('.pkpHeader__title h1').contains('Research data');
        cy.get('#datasetData > .value > p').contains(
            'Castanheiras, Íris, ' +
                currentYear +
                ', "The Rise of The Machine Empire"'
        );
        cy.get('.value > p > a').contains(
            /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//
        );
        cy.get('.value > p').contains(
            ', ' + dataverseServerName + ', DRAFT VERSION'
        );
        cy.get(
            '.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton'
        ).click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.get('.pkpPublication__versionPublished').should(
            'contain',
            'This version has been posted and can not be edited.'
        );
    });

    it('Check publish event was registered in activity log', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a')
            .contains(adminUser)
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.get('#archive-button').click();
        cy.get('#archive a:contains("View"):first').click();
        cy.get('#publication-button').click();
        cy.contains('Activity Log').click();
        cy.get('#submissionHistoryGridContainer tbody tr:first').should(
            'contain',
            'Research data published'
        );
    });

    it('Goes to preprint view page', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a')
            .contains(adminUser)
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.get('.pkpTabs__buttons > #archive-button').click();
        cy.get('#archive a:contains("View"):first').click();
        cy.get('#publication-button').click();
        cy.get('.pkpHeader > .pkpHeader__actions > a:contains("View")').click();
        cy.waitJQuery();
    });

    it('Check Publication has Dataset Citation', function() {
        cy.get('.label').contains('Research data');
        cy.get('.data_citation .value').contains(
            'Castanheiras, Íris, ' +
                currentYear +
                ', "The Rise of The Machine Empire"'
        );
        cy.get('.data_citation .value a').contains(
            /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//
        );
        cy.get('.data_citation .value').contains(
            ', ' + dataverseServerName + ', V1'
        );
    });

    it('Check Dataset Citation is visible for unauthenticated users', function() {
        cy.logout();
        cy.visit('index.php/' + serverPath + '/preprints');
        cy.get('.articles div .title a')
            .contains('The Rise of The Machine Empire')
            .click();
        cy.get('.label').contains('Research data');
        cy.get('.data_citation .value').contains(
            'Castanheiras, Íris, ' +
                currentYear +
                ', "The Rise of The Machine Empire"'
        );
        cy.get('.data_citation .value a').contains(
            /https:\/\/doi\.org\/10\.[^\/]*\/FK2\//
        );
        cy.get('.data_citation .value').contains(
            ', ' + dataverseServerName + ', V1'
        );
    });
});

describe('Create Submission without research data files', function() {
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

        cy.DataverseCreateSubmission(submissionDataNoFiles);
    });

    it('Check deposit event was not registered in activity log', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a')
            .contains(adminUser)
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.get('#myQueue a:contains("View"):first').click();
        cy.get('#publication-button').click();
        cy.contains('Activity Log').click();
        cy.get('#submissionHistoryGridContainer').should(
            'not.have.value',
            'Research data deposited:'
        );
    });

    it('Check publish event was not registered in activity log', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a')
            .contains(adminUser)
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.get('#myQueue a:contains("View"):first').click();
        cy.get('#publication-button').click();
        cy.get(
            '.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton'
        ).click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.get('.pkpPublication__versionPublished').should(
            'contain',
            'This version has been posted and can not be edited.'
        );
        cy.contains('Activity Log').click();
        cy.get('#submissionHistoryGridContainer tbody tr:first').should(
            'not.contain',
            'Research data published'
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
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
        cy.get(
            '.pkpPublication__versionPublished:contains("This version has been posted and can not be edited.")'
        );
        cy.get('.pkpPublication__status span').contains('Posted');
        cy.get('button')
            .contains('Delete research data')
            .should('be.disabled');
        cy.get(
            'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
        ).should('be.disabled');
        cy.get('button')
            .contains('Add research data')
            .should('be.disabled');
        cy.get(
            '#datasetFiles .listPanel__item .listPanel__itemActions button'
        ).should('be.disabled');
    });
});
