<?php

class DataverseStudy extends DataObject {
	
	/**
	 * Get study ID.
	 * @return int
	 */
	function getId() {
		return $this->getData('studyId');
	}

	/**
	 * Set study ID.
	 * @param $studyId int
	 */
	function setId($studyId) {
		return $this->setData('studyId', $studyId);
	}

	/**
	 * Get ID of submission associated with study.
	 * @return int
	 */
	function getSubmissionId() {
		return $this->getData('submissionId');
	}

	/**
	 * Set submission ID for study.
	 * @param $submissionId int
	 */
	function setSubmissionId($submissionId) {
		return $this->setData('submissionId', $submissionId);
	}
	
	/**
	 * Get study's edit URI.
	 * @return string
	 */
	function getEditUri() {
		return $this->getData('editUri');
	}

	/**
	 * Set study's edit URI.
	 * @param $editUri string
	 */
	function setEditUri($editUri) {
		return $this->setData('editUri', $editUri);
	}	 
	
	/**
	 * Get study's edit media URI.
	 * @return string
	 */
	function getEditMediaUri() {
		return $this->getData('editMediaUri');
	}

	/**
	 * Set study's edit media URI.
	 * @param $editMediaUri string
	 */
	function setEditMediaUri($editMediaUri) {
		return $this->setData('editMediaUri', $editMediaUri);
	}	 

	/**
	 * Get study's statement URI.
	 * @return string
	 */
	function getStatementUri() {
		return $this->getData('statementUri');
	}

	/**
	 * Set study's statement URI.
	 * @param $statementUri string
	 */
	function setStatementUri($statementUri) {
		return $this->setData('statementUri', $statementUri);
	} 
	
	/**
	 * Get study's persistent URI.
	 * @return string
	 */
	function getPersistentUri() {
		return $this->getData('persistentUri');
	}
	
	/**
	 * Set study's persistent URI.
	 * @param string $persistentUri
	 */
	function setPersistentUri($persistentUri) {
		$this->setData('persistentUri', $persistentUri);
	}
	
	/**
	 * Get data citation. 
	 * @return string
	 */
	function getDataCitation() {
		return $this->getData('dataCitation');
	}
	
	/**
	 * Set data citation.
	 * @param string $dataCitation
	 */
	function setDataCitation($dataCitation) {
		$this->setData('dataCitation', $dataCitation);
	}

}

?>