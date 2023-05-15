const template = pkp.Vue.compile(`
	<div class="dataStatementListPanel">
		<list-panel
			:expanded="expanded"
			:items="items"
		>
			<pkp-header slot="header">
				<h2>{{ title }}</h2>
				<template slot="actions">
					<pkp-button @click="openEditModal">{{ __('common.edit') }}</pkp-button>
				</template>
			</pkp-header>
			<template v-slot:itemExpanded="{item}">
				<template v-if="hasUrls(item)">
					<ul>
						<li v-for="url in item.urls">
							<a :href="url" target="_new">{{ url }}</a>
						</li>
					</ul>
				</template>

				<template v-if="hasReason(item)">
					<ul>
						<li>
							<span>justificativa: {{ localize(item.reason) }}</span>
						</li>
					</ul>
				</template>
			</template>
		</list-panel>
		<modal v-bind="MODAL_PROPS" name="form" @closed="formModalClosed">
			<modal-content
				:closeLabel="__('common.close')"
				modalName="form"
				:title="activeFormTitle"
			>
				<pkp-form
					v-bind="activeForm"
					@set="updateForm"
					@success="formSuccess"
				/>
			</modal-content>
		</modal>
	</div>
`);

let components = {...pkp.controllers.Page.components, ...pkp.controllers.Container.components};

pkp.Vue.component('data-statement-list-panel', {
    name: 'DataStatementListPanel',
	components: {
		ListPanel: components.ListPanel,
		PkpHeader: components.PkpHeader,
		PkpForm: components.PkpForm,
	},
    props: {
		apiUrl: {
			type: String,
			required: true
		},
		editDataStatementLabel: {
			type: String,
			required: true
		},
        expanded: {
			type: Array,
			default() {
				return [];
			}
		},
		form: {
			type: Object,
			required: true
		},
        items: {
			type: Array,
			default() {
				return [];
			}
		},
        title: {
			type: String,
			default() {
				return '';
			}
		}
    },
    data() {
		return {
			activeForm: null,
			activeFormTitle: '',
			resetFocusTo: null,
		};
	},
	computed: {
		hasReason() {
			return (item) => item.hasOwnProperty('reason');
		},
		hasUrls() {
			return (item) => item.hasOwnProperty('urls');
		}
	},
	methods: {
		formModalClosed(event) {
			this.activeForm = null;
			this.activeFormTitle = '';
			if (this.resetFocusTo) {
				this.resetFocusTo.focus();
			}
		},
		formSuccess(item) {
			this.$modal.hide('form');
		},
		openEditModal() {
			this.resetFocusTo = document.activeElement;
			let activeForm = JSON.parse(JSON.stringify(this.form));
			activeForm.action = this.apiUrl;
			activeForm.method = 'PUT';
			this.activeForm = activeForm;
			this.activeFormTitle = this.editDataStatementLabel;
			this.$modal.show('form');
		},
		updateForm(formId, data) {
			let activeForm = {...this.activeForm};
			Object.keys(data).forEach(function(key) {
				activeForm[key] = data[key];
			});
			this.activeForm = activeForm;
		}
	},
    render: function(h) {
		return template.render.call(this, h);
	}
});