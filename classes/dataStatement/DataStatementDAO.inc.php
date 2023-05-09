<?php

import('lib.pkp.classes.db.SchemaDAO');
import('plugins.generic.dataverse.classes.dataStatement.DataStatement');

class DataStatementDAO extends SchemaDAO
{
    public $schemaName = 'dataStatement';

    public $tableName = 'data_statements';

    public $settingsTableName = 'data_statement_settings';

    public $primaryKeyColumn = 'data_statement_id';

    public $primaryTableColumns = [
        'id' => 'data_statement_id',
        'type' => 'type',
    ];

    public function newDataObject()
    {
        return new DataStatement();
    }
}
