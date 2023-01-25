<?php

class ResponseDatasetFactory extends DatasetFactory
{
    private $responseData;

    public function __construct(array $responseData)
    {
        $this->responseData = $responseData;
    }

    protected function sanitizeProps(): array
    {
        $props = $this->responseData;

        $props['authors'] = array_map(function (array $author) {
            return new DatasetAuthor(
                $author['name'],
                $author['affiliation'],
                $author['identifier']
            );
        }, $props['authors']);

        $props['contact'] = new DatasetContact(
            $props['contact']['name'],
            $props['contact']['email'],
            $props['contact']['affiliation']
        );

        return $props;
    }
}
