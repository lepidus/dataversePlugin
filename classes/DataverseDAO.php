<?php

namespace APP\plugins\generic\dataverse\classes;

use Illuminate\Support\Facades\DB;

class DataverseDAO
{
    public function getSubmissionIdByDoi(string $doi): ?int
    {
        return DB::table('submissions as s')
            ->leftJoin('publications as p', 'p.submission_id', '=', 's.submission_id')
            ->leftJoin('dois as d', 'd.doi_id', '=', 'p.doi_id')
            ->where('d.doi', '=', $doi)
            ->value('s.submission_id');
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
        return DB::table('submissions as s')
            ->leftJoin('publications as p', 's.current_publication_id', '=', 'p.publication_id')
            ->leftJoin('publication_settings as ps', 'p.publication_id', '=', 'ps.publication_id')
            ->where('s.submission_id', '=', $submissionId)
            ->where('ps.setting_name', '=', $settingName)
            ->value('ps.setting_value');
    }
}
