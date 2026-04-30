<?php

namespace APP\plugins\generic\dataverse\classes;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;

class CrossrefXmlEditor
{
    private const RELATIONS_NAMESPACE = 'http://www.crossref.org/relations.xsd';

    private DatasetActions $datasetActions;

    public function __construct(?DatasetActions $actions = null)
    {
        $this->datasetActions = $actions ?? (new DatasetActions());
    }

    public function addDatasetRelationToDepositXml(DOMDocument $depositXml): DOMDocument
    {
        $submissionNodes = $depositXml->getElementsByTagName('journal_article');
        if ($submissionNodes->count() == 0) {
            $submissionNodes = $depositXml->getElementsByTagName('posted_content');
        }

        foreach ($submissionNodes as $submissionNode) {
            $doiDataNode = $submissionNode->getElementsByTagName('doi_data')->item(0);
            $doiNode = $doiDataNode->getElementsByTagName('doi')->item(0);
            $doi = $doiNode->nodeValue;

            $submissionId = DB::table('submissions as s')
                ->leftJoin('publications as p', 'p.submission_id', '=', 's.submission_id')
                ->leftJoin('dois as d', 'd.doi_id', '=', 'p.doi_id')
                ->where('d.doi', '=', $doi)
                ->value('s.submission_id');

            if (!$submissionId) {
                continue;
            }

            $study = Repo::dataverseStudy()->getBySubmissionId($submissionId);
            if (!$study) {
                continue;
            }

            try {
                $dataset = $this->datasetActions->get($study->getPersistentId());
            } catch (DataverseException $e) {
                $error = $e->getMessage();
                error_log('Dataverse API error on Crossref export: ' . $error);

                return $depositXml;
            }

            if ($dataset->isPublished()) {
                $this->addDatasetRelationToWorkNode($submissionNode, $study->getPersistentId());
            }
        }

        return $depositXml;
    }

    public function addDatasetRelationToWorkNode(DOMElement $workNode, string $persistentId): DOMElement
    {
        $doc = $workNode->ownerDocument;

        $relatedItemNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'related_item');

        $descriptionNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'description');
        $descriptionNode->appendChild($doc->createTextNode('Dataset deposited in Dataverse repository.'));

        $doi = preg_replace('/^doi:/i', '', $persistentId);

        $interWorkRelationNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'inter_work_relation');
        $interWorkRelationNode->setAttribute('relationship-type', 'isSupplementedBy');
        $interWorkRelationNode->setAttribute('identifier-type', 'doi');
        $interWorkRelationNode->appendChild($doc->createTextNode($doi));

        $relatedItemNode->appendChild($descriptionNode);
        $relatedItemNode->appendChild($interWorkRelationNode);

        $existingProgramNodes = $workNode->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'program');
        if ($existingProgramNodes->count() > 0) {
            $existingProgramNodes->item(0)->appendChild($relatedItemNode);
        } else {
            $programNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'program');
            $programNode->appendChild($relatedItemNode);
            $doiDataNode = $workNode->getElementsByTagName('doi_data')->item(0);
            $workNode->insertBefore($programNode, $doiDataNode);
        }

        return $workNode;
    }
}
