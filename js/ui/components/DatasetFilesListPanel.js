const datasetFilesListTemplate = pkp.Vue.compile(`
    <div class="listPanel">
        <div class="listPanel__header">
            <slot name="header">
                <pkp-header>
                    <h2>{{ title }}</h2>
                    <spinner v-if="isLoading"></spinner>
                    <template slot="actions">
                        <pkp-button
                            @click="openAddFileModal"
                        >
                            {{ addFileLabel }}
                        </pkp-button>
                    </template>
                </pkp-header>
                <modal 
                    name="addDatasetFileModal"
                    :title="modalTitle"
                    :closeLabel="__('common.close')"
                >
                    <pkp-form
                        v-bind="form"
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
                                        <a :href="getFileDownloadUrl(item)">
                                            {{ item.fileName }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </slot>
                    </li>
                </ul>
            </div>
        </div>
    </div>
`);

const ListPanel = pkp.controllers.Container.components.ListPanel;

pkp.Vue.component('dataset-files-list-panel', {
    name: 'DatasetFilesListPanel',
    extends: ListPanel,
    data() {
        return {
            isLoading: false,
        }
    },
    props: {
        addFileLabel: {
            type: String,
        },
        modalTitle: {
            type: String,
        },
        apiUrl: {
            type: String,
        },
        form: {
			type: Object,
			required: true,
		},
    },
    methods: {
        getFileDownloadUrl(item) {
            return (
                this.downloadFileUrl +
                `?fileId=${item.id}&filename=${item.fileName}`
            );
        },
        openAddFileModal() {
            this.$modal.show('addDatasetFileModal');
        },
    },
    render: function (h) {
        return datasetFilesListTemplate.render.call(this, h);
    }
});