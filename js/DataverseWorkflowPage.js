var DataverseWorkflowPage = $.extend(true, {}, pkp.controllers.WorkflowPage, {
    name: 'DataverseWorkflowPage',
    data() {
        return {
            datasetCitation: '',
            datasetCitationUrl: null,
            fileFormErrors: [],
            isLoading: false,
            latestGetRequest: '',
            dataset: null,
        };
    },
    computed: {
        isFilesEmpty: function () {
            return this.components.datasetFiles.items.length === 0;
        },

        isPosted: function () {
            return (
                this.workingPublication.status === pkp.const.STATUS_PUBLISHED
            );
        },

        researchDataCount: function () {
            if (this.dataset) {
                return this.components.datasetFiles.items.length.toString();
            }
            return '0';
        },
    },
    methods: {
        checkTermsOfUse() {
            $('input[name="termsOfUse"]').on('change', (e) => {
                this.validateTermsOfUse($(e.target).is(':checked'));
            });
        },

        fileFormSuccess(data) {
            this.refreshItems();
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
                            self.setItems(
                                self.components.datasetFiles.items.filter(
                                    (i) => i.id !== id
                                )
                            );
                            self.$modal.hide('delete');
                            self.setFocusIn(self.$el);
                        },
                    });
                },
            });
        },

        refreshItems() {
            var self = this;
            this.isLoading = true;
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
                    self.setItems(r.items);
                },
                complete() {
                    if (self.latestGetRequest !== this._uuid) {
                        return;
                    }
                    self.isLoading = false;
                },
            });
        },

        setDatasetForms(publication) {
            let form = { ...this.components.datasetMetadata };
            form.canSubmit =
                this.canEditPublication &&
                publication.status !== pkp.const.STATUS_PUBLISHED;

            this.components.datasetMetadata = {};
            this.components.datasetMetadata = form;
        },

        setDatasetCitation() {
            if (!this.datasetCitationUrl) {
                return;
            }
            this.datasetCitation = 'loading...';
            $.ajax({
                url: this.datasetCitationUrl,
                type: 'GET',
                error: this.ajaxErrorCallback,
                success: (r) => {
                    this.datasetCitation = r.citation;
                },
            });
        },

        setItems(items) {
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
        pkp.eventBus.$on('form-success', (formId) => {
            if (formId === 'datasetMetadata') {
                this.setDatasetCitation();
            }
        });

        this.setDatasetCitation();
        this.setDatasetForms(this.workingPublication);
        this.fileFormErrors = this.components.datasetFileForm.errors;
    },
    watch: {
        workingPublication(newVal, oldVal) {
            this.setDatasetCitation();
            this.setDatasetForms(newVal);
        },
    },
});

pkp.controllers['DataverseWorkflowPage'] = DataverseWorkflowPage;
