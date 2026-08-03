function getNowDateAndHour() {
    let now = new Date().toISOString();
    let nowFormatted = now.replace(/[-:T]/g, '');

    return (nowFormatted.split('.')[0]);
}

describe("Dataverse plugin - Report generation", function() {
    it("Generates Dataverse report", function() {
        cy.login('dbarnes', null, 'publicknowledge');

        cy.contains('a.app__navItem', 'Reports').click();
        cy.contains('a', 'Dataverse Report').click();

        cy.contains('h1', 'Dataverse Report')
        cy.contains('h2', 'Period');
        cy.contains('Select the desired submitted date range for filtering');
        cy.contains('legend', 'Submitted date range');
        cy.get('input#startSubmissionDateInterval');
        cy.get('input#endSubmissionDateInterval');

        cy.contains('Generate Report').click();
        cy.wait(2000);

        let now = getNowDateAndHour();
        const downloadsFolder = Cypress.config('downloadsFolder');
        const reportFileName = 'dataverse-' + now + '.csv';

        cy.readFile(downloadsFolder + reportFileName, 'utf-8').then((text) => {
            expect(text).to.contain('Articles,Reviews');
        });
    });
});