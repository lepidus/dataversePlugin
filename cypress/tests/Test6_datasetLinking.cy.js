import '../support/commands.js';

const api = '/index.php/publicknowledge/api/v1';

function requestFixture(options, expectedStatus = 200) {
    return cy.request({...options, failOnStatusCode: false, log: false}).then((response) => {
        const fields = Object.keys(response.body || {}).join(', ');
        expect(response.status, `Fixture API response (${fields})`).to.equal(expectedStatus);
        return response;
    });
}

function getFixtureCsrfToken() {
    cy.visit('/index.php/publicknowledge/user/profile');
    cy.window({log: false}).then((window) => {
        cy.wrap(window.pkp.currentUser.csrfToken, {log: false}).as('csrfToken');
    });
}

function createSubmission(title, submissions) {
    return cy.get('@csrfToken').then((csrfToken) => {
        const headers = {'X-Csrf-Token': csrfToken};
        const section = Cypress.env('contextTitles').en === 'Public Knowledge Preprint Server'
            ? {} : {sectionId: 1};
        return requestFixture({
            method: 'POST', url: `${api}/submissions`, headers,
            body: {locale: 'en', ...section}, log: false,
        }).then(({body}) => {
            submissions.push(body.id);
            return requestFixture({
                method: 'PUT',
                url: `${api}/submissions/${body.id}/publications/${body.currentPublicationId}`,
                headers,
                body: {
                    title: {en: title}, abstract: {en: 'Dataset linking fixture.'},
                    dataStatementTypes: [1],
                },
                log: false,
            }).then(() => {
                cy.visit(`/index.php/publicknowledge/submission?id=${body.id}`);
                cy.advanceSubmissionSteps();
                cy.uploadSubmissionFiles([{
                    file: 'dummy.pdf', fileName: 'dummy.pdf', mimeType: 'application/pdf',
                    genre: Cypress.env('defaultGenre'),
                }]);
                return requestFixture({
                    method: 'PUT', url: `${api}/submissions/${body.id}/submit`, headers,
                });
            }).then(() => body.id);
        });
    });
}

function openDatasetTab(submissionId, hasDataset, author = false) {
    cy.observeDataverseWorkflow();
    const page = author ? 'authorDashboard/submission' : 'workflow/access';
    cy.visit(`/index.php/publicknowledge/${page}/${submissionId}`);
    if (hasDataset) {
        cy.waitDatasetTabLoading();
    } else {
        cy.waitDataStatementTabLoading();
    }
    cy.get('#publication-button').click();
    cy.get('#datasetTab-button').click();
}

describe('Dataverse Plugin - Dataset linking', function () {
    let submissions;
    let ownerId;
    let targetId;
    let persistentId;
    let depositAttempted;
    let detached;

    beforeEach(function () {
        submissions = [];
        persistentId = null;
        depositAttempted = false;
        detached = false;
        cy.configureDataverseForTests();
        cy.loginDataverseUser('eostrom');
        getFixtureCsrfToken();
        const suffix = `${Date.now()}-${Cypress._.uniqueId()}`;
        createSubmission(`Dataset owner ${suffix}`, submissions).then((id) => { ownerId = id; });
        createSubmission(`Dataset target ${suffix}`, submissions).then((id) => { targetId = id; });
    });

    afterEach(function () {
        if (!submissions.length) {
            return;
        }
        // Remove only this case's fixtures, including a draft left disassociated by a failed assertion.
        cy.loginDataverseUser('dbarnes');
        getFixtureCsrfToken();
        cy.get('@csrfToken').then((csrfToken) => {
            const headers = {'X-Csrf-Token': csrfToken};
            if (depositAttempted) {
                if (detached) {
                    requestFixture({
                        method: 'POST', url: `${api}/datasets/associate?submissionId=${ownerId}`,
                        headers, body: {datasetPersistentId: persistentId}, log: false,
                    });
                }
                // Discover the current study even if the test failed after a successful deposit.
                // Keep local fixtures if remote cleanup cannot be confirmed.
                openDatasetTab(ownerId, true);
                cy.get('#datasetMetadata-datasetTitle-control').closest('form').invoke('attr', 'action')
                    .then((action) => {
                        requestFixture({
                            method: 'POST', url: action,
                            headers: {...headers, 'X-Http-Method-Override': 'DELETE'},
                            body: {sendDeleteEmail: 0}, log: false,
                        });
                        requestFixture({url: action}, 404);
                    });
            }
            submissions.forEach((id) => {
                requestFixture({method: 'DELETE', url: `${api}/submissions/${id}`, headers, log: false});
            });
        });
    });

    it('rejects invalid links and lets only an editor disassociate and reassociate a dataset', function () {
        // Deposit one draft through the workflow; submission creation is only a precondition.
        cy.loginDataverseUser('dbarnes');
        openDatasetTab(ownerId, false);
        cy.contains('button', 'Upload research data').click();
        cy.uploadResearchDataFile('example.json', 'linking-data.json', 'application/json', 'utf8');
        cy.uploadResearchDataFile('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', 'README.pdf', 'application/pdf', 'base64');

        cy.get('#datasetMetadata-datasetLanguage-control').select('English');
        cy.get('#datasetMetadata-datasetSubject-control').select('Earth and Environmental Sciences');
        cy.get('#datasetMetadata-datasetLicense-control').select('CC0 1.0');
        cy.get('#datasetMetadata-datasetRelationType-control').select('Is Cited By');
        cy.then(() => { depositAttempted = true; });
        cy.saveDatasetMetadata();
        cy.get('#datasetData .value a[href*="doi.org/"]').invoke('attr', 'href').then((uri) => {
            persistentId = `doi:${uri.split('doi.org/')[1]}`;
        });

        // A separate, local fixture replaces the published dataset from Test1/Test2.
        openDatasetTab(targetId, false);
        cy.get('#associateDatasetButton').click();
        cy.then(() => {
            cy.get('[data-modal="associateResearchData"] input[name="datasetPersistentId"]')
                .type(persistentId, {delay: 0});
        });
        cy.contains('[data-modal="associateResearchData"] button', 'Associate').click();
        cy.contains('The dataset entered is already associated with a submission in this context').should('be.visible');
        cy.get('[data-modal="associateResearchData"] input[name="datasetPersistentId"]')
            .clear().type('doi:10.12345/FK2/BLABLA.TESTE', {delay: 0});
        cy.contains('[data-modal="associateResearchData"] button', 'Associate').click();
        cy.contains('The dataset entered is not present at the Dataverse repository').should('be.visible');

        // Authors can view the deposited data but cannot change its association.
        cy.loginDataverseUser('eostrom');
        openDatasetTab(ownerId, true, true);
        cy.get('#disassociateDatasetButton').should('not.exist');

        cy.loginDataverseUser('dbarnes');
        openDatasetTab(ownerId, true);
        cy.get('#disassociateDatasetButton').click();
        cy.contains('The dataset will remain in Dataverse but will no longer be accessible from this submission');
        cy.intercept('POST', /\/datasets\/\d+\/disassociate(?:\?|$)/).as('disassociateDataset');
        cy.contains('[data-modal="disassociateDataset"] button', 'Disassociate').click();
        cy.wait('@disassociateDataset').its('response.statusCode').should('eq', 200)
            .then(() => { detached = true; });
        cy.contains('No research data transferred.').should('be.visible');

        cy.loginDataverseUser('eostrom');
        openDatasetTab(ownerId, false, true);
        cy.get('#associateDatasetButton').should('not.exist');

        // Observe the reload requests before confirming the association.
        cy.loginDataverseUser('dbarnes');
        openDatasetTab(ownerId, false);
        cy.get('#associateDatasetButton').click();
        cy.then(() => {
            cy.get('[data-modal="associateResearchData"] input[name="datasetPersistentId"]')
                .type(persistentId, {delay: 0});
        });
        cy.observeDataverseWorkflow();
        cy.intercept('POST', '**/api/v1/datasets/associate*').as('associateDataset');
        cy.contains('[data-modal="associateResearchData"] button', 'Associate').click();
        cy.wait('@associateDataset').its('response.statusCode').should('eq', 200)
            .then(() => { detached = false; });
        cy.waitDatasetTabLoading();
        cy.get('#publication-button').click();
        cy.get('#datasetTab-button').click();
        cy.then(() => {
            cy.contains('#datasetData a', persistentId.replace('doi:', 'https://doi.org/')).should('be.visible');
        });
        cy.get('#disassociateDatasetButton').should('be.visible');
    });
});
