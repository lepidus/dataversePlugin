var DataverseWorkflowPage = $.extend(true, {}, pkp.controllers.WorkflowPage, {
    name: 'DataverseWorkflowPage',
    data() {
        return {
            dataversePluginApiUrl: null,
            rootDataverseName: '',
            dataverseName: '',
            dataverseLicenses: [],
            dataset: null,
            datasetCitation: '',
            datasetCitationUrl: null,
            fileFormErrors: [],
            hasDepositedDataset: false,
            datasetIsLoading: true,
            isLoading: false,
            latestGetRequest: '',
            flagMounted: false
        };
    },
    computed: {
        isFilesEmpty: function () {
            return this.components.datasetFiles.items.length === 0;
        },

        datasetIsPublished: function () {
            if (this.datasetIsLoading || this.dataset == null) {
                return false;
            }

            return this.dataset.versionState === 'RELEASED';
        },

        researchDataCount: function () {
            if (this.dataset) {
                return this.components.datasetFiles.items.length.toString();
            }
            return '0';
        },

        researchDataState: function () {
            const states = {
                'inManuscript': this.__('plugins.generic.dataverse.researchDataState.inManuscript.description'),
                'repoAvailable': this.__(
                    'plugins.generic.dataverse.researchDataState.repoAvailable.description',
                    { 'researchDataUrl': this.workingPublication.researchDataUrl }
                ).replace('{$researchDataUrl}', this.workingPublication.researchDataUrl),
                'onDemand': this.__(
                    'plugins.generic.dataverse.researchDataState.onDemand.description'
                ),
                'private': this.__(
                    'plugins.generic.dataverse.researchDataState.private.description',
                    { 'researchDataReason': this.workingPublication.researchDataReason }
                )
            };

            return states[this.workingPublication.researchDataState] || this.__('plugins.generic.dataverse.researchData.noResearchData');
        }
    },
    methods: {
        openDeleteDatasetModal() {
            if (this.canSendEmail) {
                this.$modal.show('deleteDataset');
            } else {
                this.openDialog({
                    name: 'deleteDatasetAuthor',
                    title: this.deleteDatasetLabel,
                    message: this.confirmDeleteDatasetMessage,
                    actions: [
                        {
                            label: this.deleteDatasetLabel,
                            isWarnable: true,
                            callback: () => {
                                let self = this;
                                $.ajax({
                                    url: this.components.datasetMetadata.action,
                                    type: 'POST',
                                    headers: {
                                        'X-Csrf-Token': pkp.currentUser.csrfToken,
                                        'X-Http-Method-Override': 'DELETE',
                                    },
                                    error: self.ajaxErrorCallback,
                                    success: function (r) {
                                        self.$modal.hide('deleteDatasetAuthor');
                                        location.reload();
                                    },
                                });
                            }
                        }
                    ]
                });
            }
        },

        openPublishDatasetModal() {
            this.openDialog({
                name: 'publishDataset',
                title: this.publishDatasetLabel,
                message: this.confirmPublishDatasetMessage,
                actions: [
                    {
                        label: this.__('common.yes'),
                        isWarnable: false,
                        callback: () => {
                            let self = this;
                            $.ajax({
                                url: this.components.datasetMetadata.action + '/publish',
                                type: 'POST',
                                headers: {
                                    'X-Csrf-Token': pkp.currentUser.csrfToken,
                                    'X-Http-Method-Override': 'PUT',
                                },
                                error: this.ajaxErrorCallback,
                                success: function (r) {
                                    setTimeout(() => {
                                        self.dataset = r;
                                    }, 2500);
                                    self.$modal.hide('publishDataset');
                                },
                            });
                        }
                    }
                ]
            });
        },

        getRootDataverseName() {
            let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/rootDataverseName',
				type: 'GET',
				success: function (r) {
					self.rootDataverseName = r.rootDataverseName;
                    self.confirmPublishDatasetMessage = self.confirmPublishDatasetMessage.replace('{$serverName}', self.rootDataverseName);
				},
			});
        },

        getDataverseName() {
            let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/dataverseName',
				type: 'GET',
				success: function (r) {
					self.dataverseName = r.dataverseName;
                    
                    if (self.hasDepositedDataset) {
                        let deleteDatasetForm = self.components.deleteDataset;
                        let deleteMessageField = deleteDatasetForm.fields[1];
                        deleteMessageField.value = deleteMessageField.value.replace('{$dataverseName}', self.dataverseName);
                    }
				},
			});
        },

        getDataverseLicenses() {
            let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/licenses',
				type: 'GET',
				success: function (r) {
                    let datasetMetadataForm = self.components.datasetMetadata;

                    for (let license of r.licenses) {
                        self.dataverseLicenses.push({'label': license.name, 'value': license.name});
                    }

                    datasetMetadataForm.fields[4].options = self.dataverseLicenses;
				},
			});
        },

        refreshDataset() {
            const self = this;
            this.datasetIsLoading = true;
            $.ajax({
                url: this.components.datasetMetadata.action,
                type: 'GET',
                success(r) {
                    self.dataset = r;
                    self.datasetIsLoading = false;
                }
            });
        },

        updateDatasetMetadataForm(dataset) {
            let form = { ...this.components.datasetMetadata };

            for (let field of form.fields) {
                let datasetFieldName = field.name.replace(/^dataset/, '').toLowerCase();

                if (this.dataset.hasOwnProperty(datasetFieldName)) {
                    field.value = this.dataset[datasetFieldName];

                    if (datasetFieldName == 'keywords') {
                        let selectedKeywords = [];

                        for (let keyword of this.dataset[datasetFieldName]) {
                            selectedKeywords.push({'label': keyword, 'value': keyword});
                        }

                        field.selected = {};
                        field.selected[form.primaryLocale] = selectedKeywords;
                        field.value = {};
                        field.value[form.primaryLocale] = this.dataset[datasetFieldName];
                    }
                }
            }

            form.canSubmit =
                this.canEditPublication &&
                dataset.versionState !== 'RELEASED';

            this.components.datasetMetadata = {};
            this.components.datasetMetadata = form;
        },

        setDatasetFilesPanel(dataset) {
            let filesPanel = { ...this.components.datasetFiles };
            filesPanel.canChangeFiles = 
                this.canEditPublication &&
                dataset.versionState !== 'RELEASED';

            this.components.datasetFiles = {};
            this.components.datasetFiles = filesPanel;
        },

        updateDatasetCitation() {
            if (!this.datasetCitationUrl) {
                return;
            }
            var self = this;
            this.datasetCitation = this.loadingCitationMsg;
            $.ajax({
                url: self.datasetCitationUrl,
                data: {
                    datasetIsPublished: (self.datasetIsPublished ? 1 : 0)
                },
                type: 'GET',
                error: self.ajaxErrorCallback,
                success: (r) => {
                    self.datasetCitation = r.citation;
                },
            });
        },
    },
    created() {
        pkp.eventBus.$on('form-success', (formId, data) => {
            if (formId === 'datasetMetadata') {
                this.dataset = {};
                this.dataset = data;
            }
            if (formId === 'researchDataState') {
                this.workingPublication = {};
                this.workingPublication = data;
            }
            if (formId === pkp.const.FORM_PUBLISH) {
                if (!this.dataset) {
                    return;
                }
                setTimeout(() => {
                    this.refreshDataset();
                }, 2500);
            }
        });
    },
    mounted() {
        setTimeout(() => {
            this.flagMounted = true;
        }, 2500);
    },
    watch: {
        dataset(newVal, oldVal) {
            this.updateDatasetMetadataForm(newVal);
            this.setDatasetFilesPanel(newVal);
            this.updateDatasetCitation();
        },
        flagMounted(newVal, oldVal) {
            this.getDataverseName();
            this.getDataverseLicenses();

            if (this.hasDepositedDataset) {
                this.getRootDataverseName();
                this.refreshDataset();
            }
        }
    },
});

pkp.controllers['DataverseWorkflowPage'] = DataverseWorkflowPage;
