pkp.Vue.component('data-statement-form', {
	name: 'DataStatementForm',
	extends: pkp.controllers.Container.components.PkpForm,
	data() {
		return {
			allFields: null
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
		}
	},
	mounted() {
		this.allFields = this.fields;
		const newFields = this.allFields.filter(this.shouldShowField);
		this.$emit('set', this.id, { fields: newFields });
	},
});
