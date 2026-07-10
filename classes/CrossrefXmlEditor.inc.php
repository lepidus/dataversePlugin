<?php

import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudyDAO');
import('plugins.generic.dataverse.classes.DataverseDAO');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');
import('plugins.generic.dataverse.classes.services.DataStatementService');
import('plugins.generic.dataverse.classes.exception.DataverseException');

class CrossrefXmlEditor
{
    private const RELATIONS_NAMESPACE = 'http://www.crossref.org/relations.xsd';
    private const ID_TYPE_DOI = 'doi';
    private const ID_TYPE_URL = 'uri';

    private $datasetActions;

    public function __construct(?DatasetActions $actions = null)
    {
        $this->datasetActions = $actions ?? (new DatasetActions());
    }

    public function addDatasetRelationToDepositXml(DOMDocument $depositXml, int $contextId): DOMDocument
    {
        $submissionNodes = $depositXml->getElementsByTagName('journal_article');
        if ($submissionNodes->count() == 0) {
            $submissionNodes = $depositXml->getElementsByTagName('posted_content');
        }

        $dataverseDao = new DataverseDAO();
        foreach ($submissionNodes as $submissionNode) {
            $doiDataNode = $submissionNode->getElementsByTagName('doi_data')->item(0);
            $doiNode = $doiDataNode->getElementsByTagName('doi')->item(0);
            $doi = $doiNode->nodeValue;

            $submissionId = $dataverseDao->getSubmissionIdByDoi($doi, $contextId);
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
                $doi = preg_replace('/^doi:/i', '', $study->getPersistentId());
                $this->addDatasetRelationToWorkNode($submissionNode, $doi);
            }

            $dataStatementTypes = $dataverseDao->getSubmissionStatementTypes($submissionId);
            if (in_array(DATA_STATEMENT_TYPE_REPO_AVAILABLE, $dataStatementTypes)) {
                $externalDatasets = $dataverseDao->getSubmissionExternalDatasets($submissionId);

                foreach ($externalDatasets as $externalDatasetUrl) {
                    $this->addDatasetRelationToWorkNode($submissionNode, $externalDatasetUrl, true);
                }
            }
        }

        return $depositXml;
    }

    public function addDatasetRelationToWorkNode(DOMElement $workNode, string $identifier, bool $isExternalDataset = false): DOMElement
    {
        $doc = $workNode->ownerDocument;

        $relatedItemNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'related_item');

        $descriptionText = $isExternalDataset
            ? 'Dataset deposited in repository'
            : 'Dataset deposited in Dataverse repository.';
        $descriptionNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'description');
        $descriptionNode->appendChild($doc->createTextNode($descriptionText));

        $interWorkRelationNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'inter_work_relation');
        $interWorkRelationNode->setAttribute('relationship-type', 'isSupplementedBy');
        $interWorkRelationNode->setAttribute('identifier-type', ($isExternalDataset ? self::ID_TYPE_URL : self::ID_TYPE_DOI));
        $interWorkRelationNode->appendChild($doc->createTextNode($identifier));

        $relatedItemNode->appendChild($descriptionNode);
        $relatedItemNode->appendChild($interWorkRelationNode);

        $existingProgramNodes = $workNode->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'program');
        if ($existingProgramNodes->count() > 0) {
            $existingProgramNodes->item(0)->appendChild($relatedItemNode);
        } else {
            $programNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'program');
            $programNode->setAttribute('name', 'relations');
            $programNode->appendChild($relatedItemNode);
            $doiDataNode = $workNode->getElementsByTagName('doi_data')->item(0);
            $workNode->insertBefore($programNode, $doiDataNode);
        }

        return $workNode;
    }
}
