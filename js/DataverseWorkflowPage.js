var DataverseWorkflowPage = $.extend(true, {}, pkp.controllers.WorkflowPage, {
    name: 'DataverseWorkflowPage',
    data() {
        return {
            dataset: null,
            datasetCitation: '',
            datasetCitationUrl: null,
            fileFormErrors: [],
            isLoading: false,
            latestGetRequest: '',
        };
    },
    computed: {
        isFilesEmpty: function () {
            return this.components.datasetFiles.items.length === 0;
        },

        datasetIsPublished: function () {
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

        refreshDataset() {
            const self = this;
            $.ajax({
                url: this.components.datasetMetadata.action,
                type: 'GET',
                success(r) {
                    self.dataset = r;
                },
                error(r) {
                    self.ajaxErrorCallback(r);
                }
            });
        },

        setDatasetForms(dataset) {
            let form = { ...this.components.datasetMetadata };
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

        setDatasetCitation() {
            if (!this.datasetCitationUrl) {
                return;
            }
            var self = this;
            this.datasetCitation = 'loading...';
            $.ajax({
                url: self.datasetCitationUrl,
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

        this.setDatasetCitation();
        if (this.dataset) {
            this.setDatasetForms(this.dataset);
            this.setDatasetFilesPanel(this.dataset);
        }
    },
    watch: {
        dataset(newVal, oldVal) {
            this.setDatasetForms(newVal);
            this.setDatasetFilesPanel(newVal);
            this.setDatasetCitation();
        }
    },
});

pkp.controllers['DataverseWorkflowPage'] = DataverseWorkflowPage;
