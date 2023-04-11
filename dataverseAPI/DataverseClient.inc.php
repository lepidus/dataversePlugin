<?php

class DataverseClient
{
    public function getDataverseCollectionActions(): DataverseCollectionActions
    {
        import('plugins.generic.dataverse.dataverseAPI.actions.DataverseCollectionActions');
        return new DataverseCollectionActions();
    }

    public function getDatasetActions(): DatasetActions
    {
        import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');
        return new DatasetActions();
    }

    public function getDatasetFileActions(): DatasetFileActions
    {
        import('plugins.generic.dataverse.dataverseAPI.actions.DatasetFileActions');
        return new DatasetFileActions();
    }
}
