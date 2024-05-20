function addEventListeners() {
	let checkRepoAvailable = document.querySelectorAll('input[name="dataStatementTypes"][value="' + pkp.const.DATA_STATEMENT_TYPE_REPO_AVAILABLE + '"]')[0];
	let checkPublicUnavailable = document.querySelectorAll('input[name="dataStatementTypes"][value="' + pkp.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE + '"]')[0];
	let checkDataverseSubmitted = document.querySelectorAll('input[name="dataStatementTypes"][value="' + pkp.const.DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED + '"]')[0];
	
	let dataStatementUrlsField = document.getElementById('dataStatement-dataStatementUrls-description').parentNode;
	let dataStatementReasonField = document.querySelectorAll('[id^="dataStatement-dataStatementReason-description"')[0].parentNode;
	let currentUrl = window.location.href;

	dataStatementUrlsField.hidden = !checkRepoAvailable.checked;
	dataStatementReasonField.hidden = !checkPublicUnavailable.checked;

	checkRepoAvailable.addEventListener('change', function() {
		dataStatementUrlsField.hidden = !this.checked;
	});

	checkPublicUnavailable.addEventListener('change', function() {
		dataStatementReasonField.hidden = !this.checked;
	});

	if (
		document.getElementsByClassName('submissionWizard').length > 0
		&& !currentUrl.includes('workflow')
		&& !currentUrl.includes('authorDashboard')
	) {
		let panelSections = document.getElementsByClassName('panelSection');
		let datasetFilesPanel = document.getElementById('datasetFiles').parentNode.parentNode;
		let datasetSubjectField = document.getElementById('datasetMetadata-datasetSubject-control'); 
		let datasetMetadataPanel = null;

		for (let panelSection of panelSections) {
			if (panelSection.contains(datasetSubjectField)) {
				datasetMetadataPanel = panelSection;
				break;
			}
		}
		
		checkDataverseSubmitted.addEventListener('change', function() {
			if (this.checked) {
				datasetFilesPanel.style.display = '';
				datasetMetadataPanel.style.display = '';
			} else {
				datasetFilesPanel.style.display = 'none';
				datasetMetadataPanel.style.display = 'none';
			}
		});
	
		datasetFilesPanel.style.display = (checkDataverseSubmitted.checked ? '' : 'none');
		datasetMetadataPanel.style.display = (checkDataverseSubmitted.checked ? '' : 'none');
	}
}

$(document).ready(addEventListeners);