var DataverseWorkflowPage = $.extend(true, {}, pkp.controllers.WorkflowPage, {
    name: 'DataverseWorkflowPage',
    data() {
        return {
            dataversePluginApiUrl: null,
            rootDataverseName: '',
            dataverseName: '',
            dataset: null,
            datasetCitation: '',
            datasetCitationUrl: null,
            fileFormErrors: [],
            datasetIsLoading: true,
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
        checkTermsOfUse() {
            $('input[name="termsOfUse"]').on('change', (e) => {
                this.validateTermsOfUse($(e.target).is(':checked'));
            });
        },

        fileFormSuccess(data) {
            this.refreshDatasetFiles();
            this.$modal.hide('fileForm');
        },

        getFileDownloadUrl(item) {
            return (
                this.components.datasetFileForm.action +
                `?fileId=${item.id}&filename=${item.fileName}`
            );
        },

        openAddFileModal() {
            this.components.datasetFileForm.fields.map((f) => (f.value = ''));

            this.components.datasetFileForm.errors = this.fileFormErrors;

            this.$modal.show('fileForm');
        },

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

        openDeleteFileModal(id) {
            const datasetFile = this.components.datasetFiles.items.find(
                (d) => d.id === id
            );
            if (typeof datasetFile === 'undefined') {
                this.openDialog({
                    confirmLabel: this.__('common.ok'),
                    modalName: 'unknownError',
                    message: this.__('common.unknownError'),
                    title: this.__('common.error'),
                    callback: () => {
                        this.$modal.hide('unknownError');
                    },
                });
                return;
            }
            this.openDialog({
                cancelLabel: this.__('common.no'),
                modalName: 'delete',
                title: this.deleteDatasetFileLabel,
                message: this.replaceLocaleParams(this.confirmDeleteMessage, {
                    title: datasetFile.fileName,
                }),
                callback: () => {
                    var self = this;
                    $.ajax({
                        url:
                            this.components.datasetFiles.apiUrl +
                            '&fileId=' +
                            id +
                            '&filename=' +
                            datasetFile.fileName,
                        type: 'POST',
                        headers: {
                            'X-Csrf-Token': pkp.currentUser.csrfToken,
                            'X-Http-Method-Override': 'DELETE',
                        },
                        error: self.ajaxErrorCallback,
                        success: function (r) {
                            self.refreshDatasetFiles();
                            self.$modal.hide('delete');
                            self.setFocusIn(self.$el);
                        },
                    });
                },
            });
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

        getRootDataverseName() {
            let self = this;
			$.ajax({
				url: self.dataversePluginApiUrl + '/rootDataverseName',
				type: 'GET',
				error: function (r) {
					self.ajaxErrorCallback(r);
				},
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
				error: function (r) {
					self.ajaxErrorCallback(r);
				},
				success: function (r) {
					self.dataverseName = r.dataverseName;
                    let deleteDatasetForm = self.components.deleteDataset;
                    let deleteMessageField = deleteDatasetForm.fields[0];
                    deleteMessageField.value = deleteMessageField.value.replace('{$dataverseName}', self.dataverseName);
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
                },
                error(r) {
                    self.ajaxErrorCallback(r);
                }
            });
        },

        refreshDatasetFiles() {
            var self = this;
            this.components.datasetFiles.isLoading = true;
            this.latestGetRequest = $.pkp.classes.Helper.uuid();

            $.ajax({
                url: this.components.datasetFiles.apiUrl,
                type: 'GET',
                _uuid: this.latestGetRequest,
                error: function (r) {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.ajaxErrorCallback(r);
                },
                success: function (r) {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.setDatasetFiles(r.items);
                },
                complete() {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.components.datasetFiles.isLoading = false;
                },
            });
        },

        updateDatasetMetadataForm(dataset) {
            let form = { ...this.components.datasetMetadata };
            
            for (let field of form.fields) {
                let datasetFieldName = field.name.replace(/^dataset/, '').toLowerCase();
                field.value = this.dataset[datasetFieldName];

                if (datasetFieldName == 'keywords') {
                    let selectedKeywords = [];
                    for (let keyword of this.dataset[datasetFieldName]) {
                        selectedKeywords.push({'label': keyword, 'value': keyword});
                    }
                    field.selected = selectedKeywords;
                }
            }
            form.canSubmit =
                this.canEditPublication &&
                dataset.versionState !== 'RELEASED'

            this.components.datasetMetadata = {};
            this.components.datasetMetadata = form;
        },

        updateDatasetCitation() {
            if (!this.datasetCitationUrl) {
                return;
            }
            var self = this;
            this.datasetCitation = this.__('common.loading') + '...';
            $.ajax({
                url: self.datasetCitationUrl,
                type: 'GET',
                error: self.ajaxErrorCallback,
                success: (r) => {
                    self.datasetCitation = r.citation;
                },
            });
        },

        setDatasetFiles(items) {
            this.components.datasetFiles.items = items;
        },

        validateTermsOfUse(value) {
            let newErrors = { ...this.components.datasetFileForm.errors };
            if (!!value) {
                if (!this.components.datasetFileForm.errors['termsOfUse']) {
                    return;
                }
                delete newErrors['termsOfUse'];
                this.components.datasetFileForm.errors = newErrors;
            } else {
                if (this.components.datasetFileForm.errors['termsOfUse']) {
                    return;
                }
                newErrors['termsOfUse'] = this.fileFormErrors['termsOfUse'];
                this.components.datasetFileForm.errors = newErrors;
            }
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

        this.getRootDataverseName();
        this.getDataverseName();
        this.refreshDataset();
        this.refreshDatasetFiles();
        this.fileFormErrors = this.components.datasetFileForm.errors;
    },
    watch: {
        dataset(newVal, oldVal) {
            this.updateDatasetMetadataForm(newVal);
            this.updateDatasetCitation();
        }
    },
});

pkp.controllers['DataverseWorkflowPage'] = DataverseWorkflowPage;
