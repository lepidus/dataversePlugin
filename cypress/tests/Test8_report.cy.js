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
        cy.contains('Select the desired filtering type');
        cy.get('#selectFilterTypeDate').within(() => {
            cy.contains('option', 'Filter by submitted date');
            cy.contains('option', 'Filter by final decision date');
        });

        cy.contains('legend', 'Submitted date range');
        cy.get('input#startSubmissionDateInterval');
        cy.get('input#endSubmissionDateInterval');

        cy.get('#selectFilterTypeDate').select('Filter by final decision date');

        cy.contains('legend', 'Final decision date range');
        cy.get('input#startFinalDecisionDateInterval');
        cy.get('input#endFinalDecisionDateInterval');

        cy.contains('Generate Report');
    });
});
