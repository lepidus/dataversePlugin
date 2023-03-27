<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.responseEntities.DatasetFileData');

class DatasetFileDataTest extends PKPTestCase
{
    public function testConstructorSetsProperties(): void
    {
        $data = [
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
        ];
        $datasetFileData = new DatasetFileData($data);

        $this->assertEquals($data['id'], $datasetFileData->getId());
        $this->assertEquals($data['persistentId'], $datasetFileData->getPersistentId());
        $this->assertEquals($data['pidURL'], $datasetFileData->getPidURL());
        $this->assertEquals($data['filename'], $datasetFileData->getFilename());
        $this->assertEquals($data['contentType'], $datasetFileData->getContentType());
        $this->assertEquals($data['filesize'], $datasetFileData->getFilesize());
        $this->assertEquals($data['description'], $datasetFileData->getDescription());
        $this->assertEquals($data['storageIdentifier'], $datasetFileData->getStorageIdentifier());
        $this->assertEquals($data['rootDataFileId'], $datasetFileData->getRootDataFileId());
        $this->assertEquals($data['md5'], $datasetFileData->getMd5());
        $this->assertEquals($data['checksum'], $datasetFileData->getChecksum());
        $this->assertEquals($data['creationDate'], $datasetFileData->getCreationDate());
    }
}
