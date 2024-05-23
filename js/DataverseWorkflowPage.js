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
                self = this;
                this.openDialog({
                    cancelLabel: this.__('common.no'),
                    modalName: 'delete',
                    title: this.deleteDatasetLabel,
                    message: this.confirmDeleteDatasetMessage,
                    callback: () => {
                        $.ajax({
                            url: this.components.datasetMetadata.action,
                            type: 'POST',
                            headers: {
                                'X-Csrf-Token': pkp.currentUser.csrfToken,
                                'X-Http-Method-Override': 'DELETE',
                            },
                            error: this.ajaxErrorCallback,
                            success: function (r) {
                                self.$modal.hide('delete');
                                location.reload();
                            },
                        });
                    },
                });
            }
        },

        openPublishDatasetModal() {
            self = this;
            this.openDialog({
                cancelLabel: this.__('common.no'),
                modalName: 'publish',
                title: this.publishDatasetLabel,
                message: this.confirmPublishDatasetMessage,
                callback: () => {
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
                            self.$modal.hide('publish');
                        },
                    });
                },
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
                dataset.versionState !== 'RELEASED'

            this.components.datasetMetadata = {};
            this.components.datasetMetadata = form;
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
        }
    },
    watch: {
        dataset(newVal, oldVal) {
            this.setDatasetForms(newVal);
            this.setDatasetCitation();
        }
    },
});

pkp.controllers['DataverseWorkflowPage'] = DataverseWorkflowPage;
