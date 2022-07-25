<?php

import('lib.pkp.classes.form.Form');

class UploadDatasetForm extends Form {

	public function __construct() {
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}
}
