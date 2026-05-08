<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class DataverseDAO
{
    public function getSubmissionIdByDoi(string $doi): ?int
    {
        return Capsule::table('publications as p')
            ->leftJoin('publication_settings as ps', 'p.publication_id', '=', 'ps.publication_id')
            ->where('ps.setting_name', '=', 'pub-id::doi')
            ->where('ps.setting_value', '=', $doi)
            ->value('p.submission_id');
    }

    public function getSubmissionStatementTypes(int $submissionId): ?array
    {
        $dataStatementTypes = $this->getCurrentPublicationSettingValue($submissionId, 'dataStatementTypes') ?? '[]';
        return json_decode($dataStatementTypes, true);
    }

    public function getSubmissionExternalDatasets(int $submissionId): ?array
    {
        $dataStatementUrls = $this->getCurrentPublicationSettingValue($submissionId, 'dataStatementUrls') ?? '[]';
        return json_decode($dataStatementUrls, true);
    }

    private function getCurrentPublicationSettingValue(int $submissionId, string $settingName)
    {
        return Capsule::table('submissions as s')
            ->leftJoin('publications as p', 's.current_publication_id', '=', 'p.publication_id')
            ->leftJoin('publication_settings as ps', 'p.publication_id', '=', 'ps.publication_id')
            ->where('s.submission_id', '=', $submissionId)
            ->where('ps.setting_name', '=', $settingName)
            ->value('ps.setting_value');
    }
}
