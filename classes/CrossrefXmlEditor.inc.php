<?php

import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudyDAO');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');
import('plugins.generic.dataverse.classes.exception.DataverseException');

use Illuminate\Database\Capsule\Manager as Capsule;

class CrossrefXmlEditor
{
    private const RELATIONS_NAMESPACE = 'http://www.crossref.org/relations.xsd';

    private $datasetActions;

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

            $submissionId = Capsule::table('publications as p')
                ->leftJoin('publication_settings as ps', 'p.publication_id', '=', 'ps.publication_id')
                ->where('ps.setting_name', '=', 'pub-id::doi')
                ->where('ps.setting_value', '=', $doi)
                ->value('p.submission_id');

            if (!$submissionId) {
                continue;
            }

            $dataverseStudyDao = new DataverseStudyDAO();
            $study = $dataverseStudyDao->getStudyBySubmissionId($submissionId);
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
