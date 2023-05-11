<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.dispatchers.DataStatementDispatcher');
import('plugins.generic.dataverse.DataversePlugin');

class DataStatementDispatcherTest extends DatabaseTestCase
{
    protected function getAffectedTables(): array
    {
        return ['publications','publication_settings'];
    }

    public function testDataStatementPropsInPublicationSchema(): void
    {
        $dataStatementTypes = [2, 3, 5];
        $dataStatementUrls = ['https://example.com', 'https://link.to.data'];
        $dataStatementReason = 'Has sensitive data';

        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);

        HookRegistry::register('Schema::get::publication', [$dispatcher, 'addDataStatementToPublicationSchema']);

        $publicationDAO = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDAO->newDataObject();
        $publication->setData('submissionId', rand());
        $publication->setData('dataStatementTypes', $dataStatementTypes);
        $publication->setData('dataStatementUrls', $dataStatementUrls);
        $publication->setData('dataStatementReason', $dataStatementReason);

        $publicationId = $publicationDAO->insertObject($publication);
        $insertedPublication = $publicationDAO->getById($publicationId);

        $this->assertEquals($dataStatementTypes, $insertedPublication->getData('dataStatementTypes'));
        $this->assertEquals($dataStatementUrls, $insertedPublication->getData('dataStatementUrls'));
        $this->assertEquals($dataStatementReason, $insertedPublication->getData('dataStatementReason'));
    }
}
