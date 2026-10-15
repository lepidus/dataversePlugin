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
            cy.getDataverseStudyForTest(fixture.id).then((studyId) => {
                if (!studyId) {
                    // A failed deposit must retain its local recovery record.
                    if (!fixture.depositAttempted || fixture.datasetDeleted) {
                        deleteSubmissionFixture(fixture);
                    }
                    return;
                }
                const action = `${api}/datasets/${studyId}`;
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

function uploadFixtureFile(url, file, fileName, mimeType, fields = {}) {
    return cy.fixture(file, mimeType === 'application/json' ? 'utf8' : 'base64').then((content) => {
        return cy.window({log: false}).then({timeout: Cypress.config('responseTimeout')}, (window) => {
            const form = new window.FormData();
            const blob = mimeType === 'application/json'
                ? new window.Blob([typeof content === 'string' ? content : JSON.stringify(content)], {type: mimeType})
                : Cypress.Blob.base64StringToBlob(content, mimeType);
            form.append('file', blob, fileName);
            Object.entries(fields).forEach(([key, value]) => form.append(key, String(value)));
            return window.fetch(url, {
                method: 'POST', credentials: 'same-origin', body: form,
                headers: {'X-Csrf-Token': window.pkp.currentUser.csrfToken},
            }).then((response) => {
                expect(response.status, 'Fixture file upload HTTP status').to.equal(200);
                return response.json();
            });
        });
    });
}

function uploadManuscriptFixture(fixture) {
    cy.getDataverseTestGenres().then((genres) => {
        const genre = genres.find((item) => item.name === Cypress.env('defaultGenre'));
        expect(Boolean(genre), 'Configured manuscript genre exists').to.equal(true);
        uploadFixtureFile(`${api}/submissions/${fixture.id}/files`, 'dummy.pdf', 'dummy.pdf', 'application/pdf', {
            fileStage: 2, genreId: genre.id,
        });
    });
}

function uploadDatasetFixtureFile(fixture, file, fileName, mimeType) {
    uploadFixtureFile(`${api}/temporaryFiles`, file, fileName, mimeType).then((uploaded) => {
        cy.window({log: false}).then((window) => {
            requestFixture({
                method: 'POST', url: `${api}/draftDatasetFiles?submissionId=${fixture.id}&userId=${window.pkp.currentUser.id}`,
                headers: {'X-Csrf-Token': window.pkp.currentUser.csrfToken},
                body: {datasetFile: {temporaryFileId: uploaded.id}, termsOfUse: true},
            });
        });
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
                if (submitted && manuscript) {
                    uploadManuscriptFixture(fixture);
                }
                if (!submitted) {
                    cy.visit(`/index.php/publicknowledge/submission?id=${fixture.id}`);
                }
                if (!submitted && manuscript) {
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
    uploadDatasetFixtureFile(fixture, 'example.json', reviewFiles ? 'example.json' : 'Planilha_de_dados_ÇÕÔÁÀÃ.json', 'application/json');
    if (reviewFiles) {
        uploadDatasetFixtureFile(fixture, 'example.json', 'Raw_data.json', 'application/json');
    }
    uploadDatasetFixtureFile(fixture, '../../plugins/generic/dataverse/cypress/fixtures/README.pdf', reviewFiles ? 'README.pdf' : 'LEIAME.pdf', 'application/pdf');
    fixtureHeaders().then((headers) => {
        fixture.depositAttempted = true;
        fixture.datasetDeleted = false;
        requestFixture({
            method: 'POST', url: `${api}/datasets?submissionId=${fixture.id}`, headers, timeout: 240000,
            body: {
                datasetTitle: `Replication data for: ${fixture.title}`,
                datasetDescription: fixture.abstract || 'Research data fixture.',
                // Empty controlled vocabularies are submitted as an empty string by the form.
                datasetKeywords: {en: fixture.keywords?.length ? fixture.keywords.slice(0, 2) : ''},
                datasetLanguage: 'French', datasetSubject: 'Earth and Environmental Sciences',
                datasetLicense: 'CC BY 4.0', datasetRelationType: 'IsSupplementedBy',
            },
        });
    });
    openDatasetFixture(fixture, author !== 'dbarnes');
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
