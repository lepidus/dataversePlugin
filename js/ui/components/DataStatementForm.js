pkp.Vue.component('data-statement-form', {
	name: 'DataStatementForm',
	extends: pkp.controllers.Container.components.PkpForm,
	data() {
		return {
			allFields: null,
			flagMounted: false
		};
	},
	props: {
		dataversePluginApiUrl: {
			type: String
		}
	},
	methods: {
		shouldShowField(field) {
			const dataStatementTypesField = this.fields.find(
				(field) => field.name === 'dataStatementTypes'
			);
			if (!Array.isArray(dataStatementTypesField.value)) {
				dataStatementTypesField.value = [];
			}
			if (
				field.name === 'dataStatementUrls'
				&& !dataStatementTypesField.value.includes(pkp.const.DATA_STATEMENT_TYPE_REPO_AVAILABLE)
			) {
				this.removeError(field.name);
				return false;
			}
			if (
				field.name === 'dataStatementReason'
				&& !dataStatementTypesField.value.includes(pkp.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE)
			) {
				this.removeError(field.name);
				return false;
			}
			return true;
		},
		fieldChanged: function (name, prop, value, localeKey) {
			const newFields = this.allFields.map(field => {
				if (field.name === name) {
					if (localeKey) {
						field[prop][localeKey] = value;
					} else {
						field[prop] = value;
					}
				}
				return field;
			});
			this.$emit('set', this.id, { fields: newFields.filter(this.shouldShowField) });
			this.removeError(name, localeKey);
		},
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

					for (let field of self.allFields) {
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
		this.allFields = this.fields;
		const newFields = this.allFields.filter(this.shouldShowField);
		this.$emit('set', this.id, { fields: newFields });
		setTimeout(() => {
            this.flagMounted = true;
        }, 2500);
	},
	watch: {
        flagMounted(newVal, oldVal) {
			this.updateDataSubmittedField();
		}
	}
});
