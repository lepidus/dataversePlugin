pkp.Vue.component('data-statement-form', {
	name: 'DataStatementForm',
	extends: pkp.controllers.Container.components.PkpForm,
	data() {
		return {
			flagMounted: false
		};
	},
	props: {
		dataversePluginApiUrl: {
			type: String
		}
	},
	methods: {
		updateDataSubmittedField() {
			let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/dataverseName',
				type: 'GET',
				error: function (r) {
					return;
				},
				success: function (r) {
					let dataverseName = r.dataverseName;
					let researchDataSubmittedField = null;

					for (let field of self.fields) {
						if (field.name == 'researchDataSubmitted') {
							researchDataSubmittedField = field;
							break;
						}
					}

					let newFieldLabel = researchDataSubmittedField.options[0].label;
					newFieldLabel = newFieldLabel.replace(/<strong><\/strong>/, `<strong>${dataverseName}</strong>`);

					researchDataSubmittedField.options[0].label = newFieldLabel;
				},
			});
		}
	},
	mounted() {
		setTimeout(() => {
            this.flagMounted = true;
        }, 2500);
	},
	watch: {
		flagMounted(newVal, oldVal) {
			this.updateDataSubmittedField();
		},
		fields(newVal, oldVal) {
			const getField = (name) => newVal.find(field => field.name === name);

			const dataStatementTypesField = getField('dataStatementTypes');
			if (!dataStatementTypesField || !dataStatementTypesField.value) {
				return;
			}

			const fieldsToUpdate = [
				{ name: 'dataStatementReason', const: pkp.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE }
			];

			fieldsToUpdate.forEach(({ name, const: constValue }) => {
				const field = getField(name);
				if (field) {
					field.isRequired = dataStatementTypesField.value.includes(constValue);
					if (!field.isRequired && this.errors[field.name]) {
						this.$delete(this.errors, field.name);
					}
				}
			});
		}
	}
});

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