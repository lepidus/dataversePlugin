<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.responseEntities.DatasetFileList');

class DatasetFileListTest extends PKPTestCase
{
    public function testConstructorSetsProperties(): void
    {
        $data = [
            'files' => [
                [
                    'description' => '',
                    'label' => 'test.tsv',
                    'restricted' => false,
                    'version' => 1,
                    'datasetVersionId' => rand(),
                    'dataFile' => [
                        'id' => rand(),
                        'persistentId' => 'doi:10.5072/FK2/TEST',
                        'pidURL' => '',
                        'filename' => 'test.tsv',
                        'contentType' => 'text/tsv',
                        'filesize' => 0,
                        'description' => '',
                        'storageIdentifier' => 's3 =>//some-dataverse-org:1872377f3de-135aecf6930e',
                        'rootDataFileId' => -1,
                        'md5' => 'd41d8cd98f00b204e9800998ecf8427e',
                        'checksum' => [
                            'type' => 'MD5',
                            'value' => 'd41d8cd98f00b204e9800998ecf8427e'
                        ],
                        'creationDate' => '2000-01-01'
                    ]
                ]
            ]
        ];
        $datasetFileList = new DatasetFileList($data);

        $this->assertContainsOnlyInstancesOf(DatasetFileResponse::class, $datasetFileList->getFiles());
    }
}
