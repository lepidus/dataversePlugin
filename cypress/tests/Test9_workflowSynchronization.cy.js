import '../support/commands.js';

describe('Dataverse Plugin - Workflow request synchronization', function () {
    it('observes requests fired immediately when a submission is opened', function () {
        // Keep this regression independent of submission data and the remote repository.
        cy.intercept('GET', '**/api/v1/dataverse/dataverseName*', {
            statusCode: 200,
            body: {dataverseName: 'Fixture Dataverse'},
        });
        cy.intercept('GET', '**/api/v1/datasets/42/citation*', {
            statusCode: 200,
            body: {citation: 'Fixture citation'},
        });
        cy.intercept('GET', '**/dataverse-workflow-fixture', {
            headers: {'content-type': 'text/html'},
            body: `<!doctype html>
                <html><body>
                    <button id="active-button">Active</button>
                    <div><div>
                        <span class="listPanel__itemSubtitle">Synchronization fixture</span>
                        <button class="pkpButton" onclick="openWorkflow()">View</button>
                    </div></div>
                    <script>
                        function openWorkflow() {
                            fetch('/index.php/publicknowledge/api/v1/dataverse/dataverseName');
                            fetch('/index.php/publicknowledge/api/v1/datasets/42/citation');
                        }
                    </script>
                </body></html>`,
        });
        cy.visit('/dataverse-workflow-fixture');

        cy.findSubmission('active', 'Synchronization fixture');

        cy.waitDatasetTabLoading();
        cy.waitDataStatementTabLoading();
    });

    it('waits for the final submission instead of a review validation response', function () {
        cy.intercept('POST', '**/api/v1/submissions/42/submit', (request) => {
            const isValidation = new URLSearchParams(request.body).has('_validateOnly');
            request.reply({statusCode: isValidation ? 400 : 200, body: {}});
        });
        cy.intercept('GET', '**/dataverse-submission-fixture', {
            headers: {'content-type': 'text/html'},
            body: `<!doctype html>
                <html><body>
                    <button onclick="validateAndOpen()">Submit</button>
                    <script>
                        const url = '/index.php/publicknowledge/api/v1/submissions/42/submit';
                        function send(body) {
                            return fetch(url, {
                                method: 'POST', body,
                                headers: {'content-type': 'application/x-www-form-urlencoded'},
                            });
                        }
                        function validateAndOpen() {
                            send('_validateOnly=true').then(() => {
                                const modal = document.createElement('div');
                                modal.className = 'modal__panel';
                                const button = document.createElement('button');
                                button.textContent = 'Submit';
                                button.onclick = () => send('confirmCopyright=1').then(() => {
                                    document.body.innerHTML = '<h1>Submission complete</h1>';
                                });
                                modal.append(button);
                                document.body.append(modal);
                            });
                        }
                    </script>
                </body></html>`,
        });
        cy.visit('/dataverse-submission-fixture');

        cy.submitResearchData();
    });
});
