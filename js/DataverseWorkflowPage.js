var DataverseWorkflowPage = $.extend(true, {}, pkp.controllers.WorkflowPage, {
    name: "DataverseWorkflowPage",
    data() {
        return {
            fileFormErrors: [],
            isLoading: false,
            datasetMetadataForm: {},
        };
    },
    computed: {
        filesEmpty: function () {
            return this.components.datasetFiles.items.length === 0;
        },
    },
    methods: {
        fileFormSuccess(data) {
            this.refreshItems();
            this.$modal.hide("fileForm");
        },

        openAddFileModal() {
            this.components.datasetFileForm.fields.map((f) => (f.value = ""));

            this.$modal.show("fileForm");
        },

        openDeleteFileModal() {
            this.components.datasetFiles.items.pop();
        },
    },
});

pkp.controllers["DataverseWorkflowPage"] = DataverseWorkflowPage;
