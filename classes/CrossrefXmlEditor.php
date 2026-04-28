<?php

namespace APP\plugins\generic\dataverse\classes;

use DOMElement;

class CrossrefXmlEditor
{
    private const RELATIONS_NAMESPACE = 'http://www.crossref.org/relations.xsd';

    public function addDatasetRelationToWorkNode(DOMElement $workNode, string $persistentId): DOMElement
    {
        $doc = $workNode->ownerDocument;

        $programNode = $doc->createElementNS(self::RELATIONS_NAMESPACE, 'program');

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
        $programNode->appendChild($relatedItemNode);
        $workNode->appendChild($programNode);

        return $workNode;
    }
}
