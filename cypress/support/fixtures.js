import './commands.js';

const api = '/index.php/publicknowledge/api/v1';

export const dataStatementUrl = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM';

export function requestFixture(options, expectedStatus = 200) {
    return cy.request({...options, failOnStatusCode: false, log: false}).then((response) => {
        const error = typeof response.body?.error === 'string' && /^[a-zA-Z0-9_.]+$/.test(response.body.error)
            ? response.body.error : '';
        expect(response.status, `Fixture API ${options.method || 'GET'} response ${error}`).to.equal(expectedStatus);
        return response;
    });
}

function fixtureHeaders() {
    return cy.window({log: false}).then((window) => ({'X-Csrf-Token': window.pkp.currentUser.csrfToken}));
}

// Each test owns this list. Published datasets are retained together with their local records.
export function useDataverseFixtures({configure = true, configuration = {}} = {}) {
    const fixtures = [];
    beforeEach(function () {
        fixtures.length = 0;
        if (configure) {
            cy.configureDataverseForTests(configuration);
        }
    });
    afterEach(function () {
        if (this.currentTest.state === 'failed') {
            // Keep recovery records and the original failure, instead of failing a cleanup UI hook.
            return;
        }
        if (!fixtures.length) {
            return;
        }
        cy.loginDataverseUser('dbarnes');
        fixtures.forEach((fixture) => {
            if (!fixture.submitted && fixture.depositAttempted) {
                return;
            }
            if (!fixture.submitted || fixture.withoutDataverseConfiguration) {
                deleteSubmissionFixture(fixture);
                return;
            }
            openDatasetFixture(fixture);
            cy.get('body').then(($body) => {
                const action = $body.find('#datasetMetadata-datasetTitle-control').closest('form').attr('action');
                if (!action || !/\/datasets\/\d+(?:\?|$)/.test(action)) {
                    // A failed deposit must retain its local recovery record.
                    if (!fixture.depositAttempted || fixture.datasetDeleted) {
                        deleteSubmissionFixture(fixture);
                    }
                    return;
                }
                requestFixture({url: action}).then(({body}) => {
                    if (body.versionState !== 'DRAFT') {
                        return;
                    }
                    fixtureHeaders().then((headers) => {
                        requestFixture({
                            method: 'POST', url: action,
                            headers: {...headers, 'X-Http-Method-Override': 'DELETE'},
                            body: {sendDeleteEmail: 0},
                        });
                        requestFixture({url: action}, 404);
                        deleteSubmissionFixture(fixture);
                    });
                });
            });
        });
    });
    return fixtures;
}

function deleteSubmissionFixture(fixture) {
    fixtureHeaders().then((headers) => {
        requestFixture({url: `${api}/submissions/${fixture.id}/publications`}).then(({body}) => {
            body.items.filter((publication) => publication.status === 3).forEach((publication) => {
                requestFixture({
                    method: 'PUT', url: `${api}/submissions/${fixture.id}/publications/${publication.id}/unpublish`, headers,
                });
            });
        });
        requestFixture({method: 'DELETE', url: `${api}/submissions/${fixture.id}`, headers});
    });
}

export function uniqueSubmission(data) {
    return {...data, title: `${data.title} ${Date.now()}-${Cypress._.uniqueId()}`};
}

export function trackWizardSubmission(fixtures, data) {
    return cy.location('search').should('match', /[?&]id=\d+/).then((search) => {
        const id = Number(new URLSearchParams(search).get('id'));
        expect(id, 'Created wizard submission ID').to.be.greaterThan(0);
        const fixture = {...data, id, submitted: false};
        fixtures.push(fixture);
        return fixture;
    });
}

export function createSubmissionFixture(fixtures, data, {
    author = 'eostrom', submitted = true, manuscript = true, statementTypes = [2, 5],
} = {}) {
    cy.loginDataverseUser(author);
    return fixtureHeaders().then((headers) => {
        const section = Cypress.env('contextTitles').en === 'Public Knowledge Preprint Server' ? {} : {sectionId: 1};
        return requestFixture({method: 'POST', url: `${api}/submissions`, headers, body: {locale: 'en', ...section}})
            .then(({body}) => {
                const fixture = {...data, id: body.id, publicationId: body.currentPublicationId, author, statementTypes, submitted: false};
                fixtures.push(fixture);
                requestFixture({
                    method: 'PUT', url: `${api}/submissions/${fixture.id}/publications/${fixture.publicationId}`, headers,
                    body: {
                        title: {en: data.title}, abstract: {en: data.abstract || 'Research data fixture.'},
                        keywords: {en: (data.keywords || []).slice(0, 2)},
                        dataStatementTypes: submitted && statementTypes.includes(3) ? [1] : statementTypes,
                        dataStatementUrls: [dataStatementUrl],
                        dataStatementReason: {en: 'Has sensitive data'},
                    },
                });
                cy.visit(`/index.php/publicknowledge/submission?id=${fixture.id}`);
                if (manuscript) {
                    cy.advanceSubmissionSteps();
                    cy.uploadSubmissionFiles([{
                        file: 'dummy.pdf', fileName: 'dummy.pdf', mimeType: 'application/pdf', genre: Cypress.env('defaultGenre'),
                    }]);
                }
                if (submitted) {
                    requestFixture({method: 'PUT', url: `${api}/submissions/${fixture.id}/submit`, headers})
                        .then(() => { fixture.submitted = true; });
                } else {
                    cy.get('.pkpSteps__step__label').contains('Details').click();
                }
                return cy.then(() => fixture);
            });
    });
}

export function openDatasetFixture(fixture, author = false) {
    cy.observeDataverseWorkflow();
    const page = author ? 'authorDashboard/submission' : 'workflow/access';
    cy.visit(`/index.php/publicknowledge/${page}/${fixture.id}`);
    cy.waitDataStatementTabLoading();
    cy.get('#publication-button').click();
    cy.get('#datasetTab-button').click();
    cy.get('#datasetTab .pkpSpinner:visible').should('not.exist');
}

export function depositDatasetFixture(fixture, {reviewFiles = false, author = 'dbarnes'} = {}) {
    cy.loginDataverseUser(author);
    openDatasetFixture(fixture, author !== 'dbarnes');
    cy.contains('button', 'Upload research data').click();
    cy.uploadResearchDataFile('example.json', reviewFiles ? 'example.json' : 'Planilha_de_dados_ÇÕÔÁÀÃ.json', 'application/json', 'utf8');
    if (reviewFiles) {
        cy.uploadResearchDataFile('example.json', 'Raw_data.json', 'application/json', 'utf8');
    }
    cy.uploadResearchDataFile('../../plugins/generic/dataverse/cypress/fixtures/README.pdf', reviewFiles ? 'README.pdf' : 'LEIAME.pdf', 'application/pdf', 'base64');
    cy.get('#datasetMetadata-datasetLanguage-control').select('French');
    cy.get('#datasetMetadata-datasetSubject-control').select('Earth and Environmental Sciences');
    cy.get('#datasetMetadata-datasetLicense-control').select('CC BY 4.0');
    cy.get('#datasetMetadata-datasetRelationType-control').select('Is Supplemented By');
    cy.then(() => { fixture.depositAttempted = true; fixture.datasetDeleted = false; });
    cy.saveDatasetMetadata();
    cy.get('#datasetData a[href*="doi.org/"]').should('be.visible');
    if (fixture.statementTypes.includes(3)) {
        fixtureHeaders().then((headers) => {
            requestFixture({
                method: 'PUT', url: `${api}/submissions/${fixture.id}/publications/${fixture.publicationId}`,
                headers, body: {dataStatementTypes: fixture.statementTypes},
            });
        });
    }
}

export function sendFixtureForReview(fixture, {assignReviewer = false} = {}) {
    cy.loginDataverseUser('dbarnes');
    openDatasetFixture(fixture);
    cy.get('#workflow-button').click();
    cy.clickDecision('Send for Review');
    cy.contains('button', 'Skip this email').click();
    cy.contains('button', 'Continue').click();
    cy.contains('span', 'Raw_data.json').parent().find('input').check();
    cy.contains('span', 'README.pdf').parent().find('input').check();
    cy.contains('button', 'Record Decision').click();
    cy.get('a.pkpButton').contains('View Submission').click();
    if (assignReviewer) {
        cy.assignReviewer('Julie Janssen');
    }
}

export function prepareProductionFixture(fixture) {
    cy.loginDataverseUser('dbarnes');
    openDatasetFixture(fixture);
    if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
        // Editorial decisions are a precondition here; their UI is covered by workflow/review cases.
        fixtureHeaders().then((headers) => {
            [17, 7].forEach((decision) => {
                requestFixture({
                    method: 'POST', url: `${api}/submissions/${fixture.id}/decisions?decision=${decision}`,
                    headers, body: {decision},
                });
            });
        });
        openDatasetFixture(fixture);
    }
    cy.get('#publication-button').click();
}

export function publishSubmissionFixture(fixture, {publishDataset = false} = {}) {
    prepareProductionFixture(fixture);
    cy.get('div#publication button:contains("Schedule For Publication"), div#publication button:contains("Post")').click();
    if (Cypress.env('contextTitles').en !== 'Public Knowledge Preprint Server') {
        cy.get('#assignToIssue-issueId-control').select('1');
        cy.get('div[id^="assign-"] button:contains("Save")').click();
        cy.get('div[id^="assign-"] [role="status"]').contains('Saved');
        cy.reload();
        cy.get('div#publication button:contains("Schedule For Publication")').click();
    }
    cy.get('.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();
    cy.then(() => {
        if (fixture.depositAttempted && !fixture.datasetReleased) {
            cy.get(`input[name="shouldPublishResearchData"][value="${publishDataset ? 1 : 0}"]`).check();
            cy.get('.pkpWorkflow__publishModal button:contains("Publish"), .pkp_modal_panel button:contains("Post")').click();
        }
    });
    cy.get('.pkpPublication__statusPublished').should('have.text', 'Published');
}

export function publishDatasetFixture(fixture) {
    cy.loginDataverseUser('dbarnes');
    openDatasetFixture(fixture);
    cy.get('#datasetMetadata-datasetTitle-control').closest('form').invoke('attr', 'action').then((action) => {
        fixtureHeaders().then((headers) => {
            requestFixture({
                method: 'POST', url: action + '/publish',
                headers: {...headers, 'X-Http-Method-Override': 'PUT'},
            }).then(() => { fixture.datasetReleased = true; });
        });
    });
}
