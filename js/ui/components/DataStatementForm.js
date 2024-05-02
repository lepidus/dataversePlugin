function addEventListeners() {
	let checkRepoAvailable = document.querySelectorAll('input[name="dataStatementTypes"][value="' + pkp.const.DATA_STATEMENT_TYPE_REPO_AVAILABLE + '"]')[0];
	let checkPublicUnavailable = document.querySelectorAll('input[name="dataStatementTypes"][value="' + pkp.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE + '"]')[0];
	let dataStatementUrlsField = document.getElementById('dataStatement-dataStatementUrls-description').parentNode;
	let dataStatementReasonField = document.querySelectorAll('[id^="dataStatement-dataStatementReason-description"')[0].parentNode;

	dataStatementUrlsField.hidden = !checkRepoAvailable.checked;
	dataStatementReasonField.hidden = !checkPublicUnavailable.checked;

	checkRepoAvailable.addEventListener('change', function() {
		dataStatementUrlsField.hidden = !this.checked;
	});

	checkPublicUnavailable.addEventListener('change', function() {
		dataStatementReasonField.hidden = !this.checked;
	});
}

$(document).ready(addEventListeners);