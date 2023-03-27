<?php

class DatasetFileList
{
    private $files;

    public function __construct(array $data)
    {
        $this->files = array_map(function ($file) {
            return new DatasetFileResponse($file);
        }, $data['files']);
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
