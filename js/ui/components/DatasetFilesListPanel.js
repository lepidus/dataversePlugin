const datasetFilesListTemplate = pkp.Vue.compile(`
    <div>
        <div class="listPanel">
            <div class="listPanel__header">
                <slot name="header">
                    <pkp-header>
                        <h2>{{ title }}</h2>
                        <spinner v-if="isLoading"></spinner>
                        <template slot="actions">
                            <pkp-button
                                :disabled="isLoading || !canChangeFiles"
                                @click="openAddFileModal"
                            >
                                {{ addFileLabel }}
                            </pkp-button>
                        </template>
                    </pkp-header>
                    <modal 
                        name="addDatasetFileModal"
                        :title="addFileModalTitle"
                        :closeLabel="__('common.close')"
                    >
                        <pkp-form
                            v-bind="form"
                            @success="addFileFormSuccess"
                        />
                    </modal>
                </slot>
            </div>
            <div class="listPanel__body">
                <div class="listPanel__items">
                    <div v-if="Object.keys(items).length == 0" class="listPanel__empty">
                        <slot name="itemsEmpty">{{ __('common.noItemsFound') }}</slot>
                    </div>
                    <ul v-else class="listPanel__itemsList">
                        <li v-for="item in items" :key="item.id" class="listPanel__item">
                            <slot name="item" :item="item">
                                <div class="listPanel__itemSummary">
                                    <div class="listPanel__itemIdentity">
                                        <div class="listPanel__itemTitle">
                                            <a :href="item.downloadUrl">
                                                {{ item.fileName }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="listPanel__itemActions">
                                        <pkp-button
                                            :disabled="isLoading || !canChangeFiles"
                                            :isWarnable="true"
                                            @click="openDeleteFileModal(item.id, item.fileName)"
                                        >
                                            {{ __('common.delete') }}
                                        </pkp-button>
                                    </div>
                                </div>
                            </slot>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div
            id="dataverseAdditionalInstructions"
            v-if="additionalInstructions"
            v-html="additionalInstructions"
            style="margin-top: 1.5rem;line-height: 1.5rem;font-size: 0.875rem;"
        ></div>
    </div>
`);

let SubmissionWizardPage = pkp.controllers.SubmissionWizardPage;
if (!SubmissionWizardPage.hasOwnProperty('components')) {
    SubmissionWizardPage = SubmissionWizardPage.extends;
}

const ListPanel = pkp.controllers.Container.components.ListPanel;
const Modal = SubmissionWizardPage.components.Modal;
const ajaxError = SubmissionWizardPage.mixins[0];
const dialog = SubmissionWizardPage.mixins[2];

pkp.Vue.component('dataset-files-list-panel', {
    name: 'DatasetFilesListPanel',
    extends: ListPanel,
    components: {
        Modal
    },
    mixins: [ajaxError, dialog],
    data() {
        return {
            flagMounted: false,
            isLoading: false
        }
    },
    props: {
        addFileLabel: {
            type: String,
        },
        addFileModalTitle: {
            type: String,
        },
        additionalInstructions: {
            type: String,
        },
        dataversePluginApiUrl: {
            type: String,
        },
        fileListUrl: {
            type: String,
        },
        fileActionUrl: {
            type: String,
        },
        canChangeFiles: {
			type: Boolean,
			default() {
				return true;
			},
		},
        form: {
			type: Object,
		},
        deleteFileTitle: {
            type: String
        },
        deleteFileMessage: {
            type: String
        },
        deleteFileConfirmLabel: {
            type: String
        }
    },
    methods: {
        openAddFileModal() {
            this.$modal.show('addDatasetFileModal');
        },
        addFileFormSuccess(data) {
            this.refreshItems();
            this.$modal.hide('addDatasetFileModal');
        },
        openDeleteFileModal(fileId, fileName) {
            const datasetFile = Object.values(this.items).find(
                (file) => file.id === fileId
            );
            if (typeof datasetFile === 'undefined') {
                this.openDialog({
                    name: 'unknownError',
                    title: this.__('common.error'),
                    message: this.__('common.unknownError'),
                    actions: [
                        {
                            label: this.__('common.ok'),
                            callback: () => this.$modal.hide('unknownError'),
                        }
                    ]
                });
                return;
            }
            this.openDialog({
                name: 'deleteDatasetFile',
                title: this.deleteFileTitle,
                message: this.replaceLocaleParams(this.deleteFileMessage, {
                    title: datasetFile.fileName,
                }),
                actions: [
					{
						label: this.deleteFileConfirmLabel,
						isWarnable: true,
						callback: () => {
							var self = this;
                            $.ajax({
                                url: this.fileActionUrl + '?fileId=' + fileId + '&fileName=' + fileName,
                                type: 'DELETE',
                                headers: {
                                    'X-Csrf-Token': pkp.currentUser.csrfToken,
                                },
                                error: self.ajaxErrorCallback,
                                success: function (r) {
                                    self.refreshItems();
                                    self.$modal.hide('deleteDatasetFile');
                                    self.setFocusIn(self.$el);
                                },
                            });
						},
					},
					{
						label: this.__('common.cancel'),
						callback: () => this.$modal.hide('deleteDatasetFile'),
					},
				]
            });
        },
        refreshItems() {
            var self = this;
            this.isLoading = true;
            this.latestGetRequest = $.pkp.classes.Helper.uuid();

            $.ajax({
				url: this.fileListUrl,
				type: 'GET',
				_uuid: this.latestGetRequest,
				error: function (response) {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.ajaxErrorCallback(response);
                },
				success: function (response) {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.items = response.items;
                    pkp.registry._instances.app.components.datasetFiles.items = self.items;
				},
				complete() {
					if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.isLoading = false;
				},
			});
        },
        getDataverseName() {
            let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/dataverseName',
				type: 'GET',
				success: function (r) {
                    let datasetFileForm = self.form;
                    let termsOfUseFieldOption = datasetFileForm.fields[1].options[0];
                    termsOfUseFieldOption.label = termsOfUseFieldOption.label.replace('{$dataverseName}', r.dataverseName);
				},
			});
        }
    },
    render: function (h) {
        return datasetFilesListTemplate.render.call(this, h);
    },
    mounted() {
        setTimeout(() => {
            this.flagMounted = true;
        }, 2500);
    },
    watch: {
        flagMounted(newVal, oldVal) {
            this.refreshItems();
            this.getDataverseName();
        }
    }
});