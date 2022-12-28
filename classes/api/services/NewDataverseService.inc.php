<?php

class NewDataverseService
{
    private $client;

    public function __construct(DataverseAPIClient $client)
    {
        $this->client = $client;
    }

    public function createDatasetInDataverse(Submission $submission): void
    {
        $datasetProvider = $this->client->getDatasetProvider($submission);
        $files = $datasetProvider->getSubmissionFiles();

        if (!empty($files)) {
            $datasetProvider->createDataset();
            $datasetProvider->prepareDatasetFiles($files);

            $response = $this->client->depositDataset($datasetProvider);
            $persistentId = $response['persistentId'];
            $this->setDatasetPersistentId($submission, $persistentId);
            $this->client->depositDatasetFiles($persistentId, $datasetProvider);
        }
    }

    private function setDatasetPersistentId(Submission $submission, string $persistentId): void
    {
        $params = ['datasetPersistentId' => $persistentId];
        $request = Application::get()->getRequest();

        $submission = Services::get('submission')->edit($submission, $params, $request);
    }
}
