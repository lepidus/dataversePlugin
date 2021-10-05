<?php

class DataverseStudy extends DataObject {

	function getId() {
		return $this->getData('studyId');
	}

	function setId($studyId) {
		return $this->setData('studyId', $studyId);
	}

	function getSubmissionId() {
		return $this->getData('submissionId');
	}

	function setSubmissionId($submissionId) {
		return $this->setData('submissionId', $submissionId);
	}
	
	function getEditUri() {
		return $this->getData('editUri');
	}

	function setEditUri($editUri) {
		return $this->setData('editUri', $editUri);
	}	 
	
	function getEditMediaUri() {
		return $this->getData('editMediaUri');
	}

	function setEditMediaUri($editMediaUri) {
		return $this->setData('editMediaUri', $editMediaUri);
	}	 

	function getStatementUri() {
		return $this->getData('statementUri');
	}

	function setStatementUri($statementUri) {
		return $this->setData('statementUri', $statementUri);
	} 
	
	function getPersistentUri() {
		return $this->getData('persistentUri');
	}
	
	function setPersistentUri($persistentUri) {
		$this->setData('persistentUri', $persistentUri);
	}
	
	function getDataCitation() {
		return $this->getData('dataCitation');
	}
	
	function setDataCitation($dataCitation) {
		$this->setData('dataCitation', $dataCitation);
	}

}

?>