<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.services.DataStatementService');
import('plugins.generic.dataverse.classes.dataStatement.DataStatementDAO');

class DataStatementDAOTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->addDataStatementSchema();
    }

    private function addDataStatementSchema(): void
    {
        HookRegistry::register('Schema::get::dataStatement', function (string $hookname, array $params) {
            $schema = &$params[0];
            $dataStatementSchemaFile = BASE_SYS_DIR . '/plugins/generic/dataverse/schemas/dataStatement.json';

            if (file_exists($dataStatementSchemaFile)) {
                $schema = json_decode(file_get_contents($dataStatementSchemaFile));
            }

            return false;
        });
    }

    protected function getAffectedTables(): array
    {
        return ['data_statements', 'data_statement_settings'];
    }

    public function testInsertInManuscriptDataStatement(): void
    {
        $type = DATA_STATEMENT_TYPE_IN_MANUSCRIPT;

        $dataStatement = new DataStatement();
        $dataStatement->setType($type);

        $dataStatementDAO = new DataStatementDAO();
        $dataStatementId = $dataStatementDAO->insertObject($dataStatement);

        $insertedDataStatement = $dataStatementDAO->getById($dataStatementId);

        $this->assertEquals($type, $insertedDataStatement->getType());
    }

    public function testInsertRepoAvailableDataStatement(): void
    {
        $type = DATA_STATEMENT_TYPE_REPO_AVAILABLE;
        $links = ['https://link.to.data'];

        $dataStatement = new DataStatement();
        $dataStatement->setType($type);
        $dataStatement->setLinks($links);

        $dataStatementDAO = new DataStatementDAO();
        $dataStatementId = $dataStatementDAO->insertObject($dataStatement);

        $insertedDataStatement = $dataStatementDAO->getById($dataStatementId);

        $this->assertEquals($type, $insertedDataStatement->getType());
        $this->assertEquals($links, $insertedDataStatement->getLinks());
    }

    public function testInsertDataverseSubmittedDataStatement(): void
    {
        $type = DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED;
        $datasetId = 1;

        $dataStatement = new DataStatement();
        $dataStatement->setType($type);
        $dataStatement->setDatasetId($datasetId);

        $dataStatementDAO = new DataStatementDAO();
        $dataStatementId = $dataStatementDAO->insertObject($dataStatement);

        $insertedDataStatement = $dataStatementDAO->getById($dataStatementId);

        $this->assertEquals($type, $insertedDataStatement->getType());
        $this->assertEquals($datasetId, $insertedDataStatement->getDatasetId());
    }

    public function testInsertOnDemandDataStatement(): void
    {
        $type = DATA_STATEMENT_TYPE_ON_DEMAND;

        $dataStatement = new DataStatement();
        $dataStatement->setType($type);

        $dataStatementDAO = new DataStatementDAO();
        $dataStatementId = $dataStatementDAO->insertObject($dataStatement);

        $insertedDataStatement = $dataStatementDAO->getById($dataStatementId);

        $this->assertEquals($type, $insertedDataStatement->getType());
    }

    public function testInsertPubliclyUnavailableDataStatement(): void
    {
        $type = DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE;
        $reason = 'Has sensitive data';

        $dataStatement = new DataStatement();
        $dataStatement->setType($type);
        $dataStatement->setReason($reason, 'en_US');

        $dataStatementDAO = new DataStatementDAO();
        $dataStatementId = $dataStatementDAO->insertObject($dataStatement);

        $insertedDataStatement = $dataStatementDAO->getById($dataStatementId);

        $this->assertEquals($type, $insertedDataStatement->getType());
        $this->assertEquals($reason, $insertedDataStatement->getReason('en_US'));
    }
}
