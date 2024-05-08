const datasetFilesListTemplate = pkp.Vue.compile(`
    <div class="listPanel">
        <div class="listPanel__header">
            <slot name="header">
                <pkp-header>
                    <h2>{{ title }}</h2>
                    <spinner v-if="isLoading"></spinner>
                    <template slot="actions">
                        <pkp-button 
                        >
                            {{ addFileLabel }}
                        </pkp-button>
                    </template>
                </pkp-header>
            </slot>
        </div>
        <div class="listPanel__body">
            <div class="listPanel__items">
                <div v-if="!items.length" class="listPanel__empty">
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

const ListPanel =
    pkp.controllers.Container.components.ListPanel;

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
        downloadFileUrl: {
            type: String,
        }
    },
    methods: {
        getFileDownloadUrl(item) {
            return (
                this.downloadFileUrl +
                `?fileId=${item.id}&filename=${item.fileName}`
            );
        },
    },
    render: function (h) {
        return datasetFilesListTemplate.render.call(this, h);
    }
});