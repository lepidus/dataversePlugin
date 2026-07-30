import '../support/commands.js';

describe('Dataverse Plugin - Information displayed in public site', function () {
    const submissionData = {
        title: 'Public information for controlled research data',
        abstract: 'A submission prepared to verify research data on the public article page.',
        keywords: ['public research data'],
        datasetSubject: 'Arts and Humanities',
    };
    const persistentId = 'doi:10.5072/FK2/PUBLICINFO';
    let controlledDataverseUrl;
    let submissionId;

    before(function () {
        cy.startControlledDataverse().then((url) => {
            controlledDataverseUrl = url;
            cy.login('dbarnes', null, 'publicknowledge');
            cy.ensureDataversePluginEnabled();
            cy.configureDataverse({
                url,
                apiToken: 'valid-token',
                termsOfUse: 'https://example.test/terms',
                datasetPublish: 2,
            });
            cy.logout();
        });
    });

    after(function () {
        const externalDataverseConfiguration = {
            url: Cypress.env('dataverseUrl'),
            apiToken: Cypress.env('dataverseApiToken'),
            termsOfUse: Cypress.env('dataverseTermsOfUse'),
        };
        const hasExternalDataverseConfiguration = Object.values(externalDataverseConfiguration)
            .every((value) => typeof value === 'string' && value.length > 0);

        if (hasExternalDataverseConfiguration) {
            cy.login('dbarnes', null, 'publicknowledge');
            cy.configureDataverse({
                ...externalDataverseConfiguration,
                datasetPublish: 2,
            });
            cy.logout();
        }
        cy.stopControlledDataverse();
    });

    it('Displays a published dataset without a redundant data statement', function () {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.createDataverseSubmissionWithApi(submissionData);
        cy.get('@submissionId').then((id) => {
            cy.visit(`/index.php/publicknowledge/submission?id=${id}`);
        });
        cy.contains('button', 'Continue').click();
        cy.uploadSubmissionFiles([{
            file: 'dummy.pdf',
            fileName: 'article.pdf',
            mimeType: 'application/pdf',
            genre: 'Article Text',
        }]);
        cy.addDraftDatasetFile({
            fixture: 'example.json',
            fileName: 'controlled-data.json',
            mimeType: 'application/json',
            encoding: 'utf8',
        });
        cy.addDraftDatasetFile({
            fixture: '../../plugins/generic/dataverse/cypress/fixtures/README.pdf',
            fileName: 'README.pdf',
            mimeType: 'application/pdf',
            encoding: 'base64',
        });
        cy.associateDataverseDatasetWithApi(persistentId);
        cy.submitPreparedSubmissionWithApi();
        cy.get('@submissionId').then((id) => {
            submissionId = id;
        });
        cy.logout();

        cy.login('dbarnes', null, 'publicknowledge');
        cy.then(() => {
            cy.visit(`/index.php/publicknowledge/workflow/index/${submissionId}/1`);
        });

        cy.clickDecision('Accept and Skip Review');
        cy.contains('h2', 'Notify Authors');
        cy.contains('button', 'Skip this email').click();
        cy.contains('h2', 'Select Files');
        cy.contains('.listPanel__item', 'article.pdf').within(() => {
            cy.get('input[type="checkbox"]').should('be.checked');
        });
        cy.recordDecision('and has been sent to the copyediting stage');
        cy.isActiveStageTab('Copyediting');

        cy.clickDecision('Send To Production');
        cy.recordDecisionSendToProduction(['Catherine Kwantes'], []);
        cy.isActiveStageTab('Production');

        cy.get('#publication-button').click();
        cy.get('div#publication button:contains("Schedule For Publication")').click();
        cy.get('select[id="assignToIssue-issueId-control"]').select('1');
        cy.get('div[id^="assign-"] button:contains("Save")').click();
        cy.get('div[id^="assign-"] [role="status"]').contains('Saved');
        cy.reload();
        cy.get('div#publication button:contains("Schedule For Publication")').click();
        cy.contains('label', 'Yes').within(() => {
            cy.get('input').check();
        });
        cy.get('.pkpWorkflow__publishModal button:contains("Publish")').click();
        cy.contains('Status: Published');
        cy.request({
            url: controlledDataverseUrl.replace('/dataverse/testDataverse', '')
                + `/api/datasets/:persistentId/versions?persistentId=${encodeURIComponent(persistentId)}`,
            headers: {'X-Dataverse-key': 'valid-token'},
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.data[0].versionState).to.eq('RELEASED');
        });
        cy.then(() => {
            cy.visit(`/index.php/publicknowledge/article/view/${submissionId}`);
        });

        cy.contains('h2', 'Data statement').should('not.exist');
        cy.contains('h2', 'Research data');
        cy.contains('Controlled dataset');
        cy.contains('a', 'https://doi.org/10.5072/FK2/PUBLICINFO');
        cy.contains('Controlled Dataverse, V1');
    });
});
