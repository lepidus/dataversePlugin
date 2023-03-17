var DataverseWorkflowPage = $.extend(true, {}, pkp.controllers.WorkflowPage, {
    name: "DataverseWorkflowPage",
    data() {
        return {
            datasetMetadataForm: {},
            fileFormErrors: [],
            isLoading: false,
            latestGetRequest: "",
        };
    },
    computed: {
        filesEmpty: function () {
            return this.components.datasetFiles.items.length === 0;
        },
    },
    methods: {
        checkTermsOfUse() {
            $('input[name="termsOfUse"]').on("change", (e) => {
                this.validateTermsOfUse($(e.target).is(":checked"));
            });
        },

        fileFormSuccess(data) {
            this.refreshItems();
            this.$modal.hide("fileForm");
        },

        openAddFileModal() {
            this.components.datasetFileForm.fields.map((f) => (f.value = ""));

            this.components.datasetFileForm.errors = this.fileFormErrors;

            this.$modal.show("fileForm");
        },

        openDeleteFileModal(id) {
            const datasetFile = this.components.datasetFiles.items.find(
                (d) => d.id === id
            );
            if (typeof datasetFile === "undefined") {
                this.openDialog({
                    confirmLabel: this.__("common.ok"),
                    modalName: "unknownError",
                    message: this.__("common.unknownError"),
                    title: this.__("common.error"),
                    callback: () => {
                        this.$modal.hide("unknownError");
                    },
                });
                return;
            }
            this.openDialog({
                cancelLabel: this.__("common.no"),
                modalName: "delete",
                title: this.deleteDatasetFileLabel,
                message: this.replaceLocaleParams(this.confirmDeleteMessage, {
                    title: datasetFile.fileName,
                }),
                callback: () => {
                    var self = this;
                    $.ajax({
                        url: this.components.datasetFiles.apiUrl + "&id=" + id,
                        type: "POST",
                        headers: {
                            "X-Csrf-Token": pkp.currentUser.csrfToken,
                            "X-Http-Method-Override": "DELETE",
                        },
                        error: self.ajaxErrorCallback,
                        success: function (r) {
                            self.setItems(
                                self.components.datasetFiles.items.filter(
                                    (i) => i.id !== id
                                )
                            );
                            self.$modal.hide("delete");
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
                type: "GET",
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

        setItems(items) {
            this.components.datasetFiles.items = items;
        },

        validateTermsOfUse(value) {
            let newErrors = { ...this.components.datasetFileForm.errors };
            if (!!value) {
                if (!this.components.datasetFileForm.errors["termsOfUse"]) {
                    return;
                }
                delete newErrors["termsOfUse"];
                this.components.datasetFileForm.errors = newErrors;
            } else {
                if (this.components.datasetFileForm.errors["termsOfUse"]) {
                    return;
                }
                newErrors["termsOfUse"] = this.fileFormErrors["termsOfUse"];
                this.components.datasetFileForm.errors = newErrors;
            }
        },
    },
    mounted() {
        this.fileFormErrors = this.components.datasetFileForm.errors;
    },
});

pkp.controllers["DataverseWorkflowPage"] = DataverseWorkflowPage;
