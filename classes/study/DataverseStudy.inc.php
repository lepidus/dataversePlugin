<?php

class DataverseStudy extends DataObject {

	function getId(): int
	{
		return $this->getData('studyId');
	}

	function setId($studyId): void
	{
		$this->setData('studyId', $studyId);
	}

	function getSubmissionId(): int
	{
		return $this->getData('submissionId');
	}

	function setSubmissionId($submissionId): void
	{
		$this->setData('submissionId', $submissionId);
	}
	
	function getEditUri(): string
	{
		return $this->getData('editUri');
	}

	function setEditUri($editUri): void
	{
		$this->setData('editUri', $editUri);
	}	 
	
	function getEditMediaUri(): string
	{
		return $this->getData('editMediaUri');
	}

	function setEditMediaUri($editMediaUri): void
	{
		$this->setData('editMediaUri', $editMediaUri);
	}	 

	function getStatementUri(): string
	{
		return $this->getData('statementUri');
	}

	function setStatementUri($statementUri): void
	{
		$this->setData('statementUri', $statementUri);
	} 
	
	function getPersistentUri(): string
	{
		return $this->getData('persistentUri');
	}
	
	function setPersistentUri($persistentUri): void
	{
		$this->setData('persistentUri', $persistentUri);
	}
	
	function getDataCitation(): string
	{
		return $this->getData('dataCitation');
	}
	
	function setDataCitation($dataCitation): void
	{
		$this->setData('dataCitation', $dataCitation);
	}

	function getDatasetUrl(): string
	{
		return $this->getData('datasetUrl');
	}
	
	function setDatasetUrl($datasetUrl): void
	{
		$this->setData('datasetUrl', $datasetUrl);
	}

}

?>