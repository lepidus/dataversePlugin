<?php

import('plugins.generic.dataverse.classes.api.DataverseService');

class DataverseServiceFactory
{
    public function build(DataverseConfiguration $configuration, DataversePlugin $plugin): DataverseService
    {
        return new DataverseService(new DataverseClient($configuration, $plugin));
    }
}
