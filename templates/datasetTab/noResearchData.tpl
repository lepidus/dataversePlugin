<section class="researchData -pkpClearfix">
    <div class="researchData__header">
        <div class="researchData__state">
            <strong>{translate key="plugins.generic.dataverse.researchDataState.state"}:</strong>
            <span v-html="researchDataState">   </span>
        </div>
        <div class="researchData__stateButton">
            <dropdown v-if="!isPosted" label="{translate key="common.edit"}">
                <pkp-form
                    class="researchData__stateForm"
                    v-bind="components.researchDataState"
                    @set="set"
                    @success="refreshSubmission"
                >
            </dropdown>
            <pkp-button @click="$modal.show('uploadResearchData')" v-if="!isPosted">
                {translate key="plugins.generic.dataverse.researchData.uploadResearchData"}
            </pkp-button>
        </div>
    </div>
</section>

<modal 
    v-bind="MODAL_PROPS" 
    name="uploadResearchData"
>
    <modal-content 
        close-label="common.close"
        modal-name="uploadResearchData"
        title="{translate key="plugins.generic.dataverse.researchData.uploadResearchData"}"
    >
        <div class="filesList">
            <list-panel v-bind="components.datasetFiles" @set="set">
                <pkp-header slot="header">
                    <h2>{{ components.datasetFiles.title }}</h2>
                    <spinner v-if="components.datasetFiles.isLoading"></spinner>
                    <template slot="actions">
                        <pkp-button 
                            ref="fileModalButton"
                            @click="openAddFileModal"
                            :disabled="isPosted"
                        >
                            {{ components.datasetFiles.addFileLabel }}
                        </pkp-button>
                    </template>
                </pkp-header>
                <template v-slot:item="item">
                    <div class="listPanel__itemSummary">
                        <div class="listPanel__itemIdentity">
                            <div class="listPanel__itemTitle">
                                <a :href="item.item.fileDownloadUrl">
                                    {{ item.item.fileName }}
                                </a>
                            </div>
                        </div>
                        <div class="listPanel__itemActions">
                            <pkp-button 
                                @click="openDeleteFileModal(item.item.id)"
                                :disabled="isPosted"
                                class="pkpButton--isWarnable"
                            >
                                {{ __('common.delete') }}
                            </pkp-button>
                        </div>
                    </div>
                </template>
            </list-panel>
            <modal 
                v-bind="MODAL_PROPS" 
                name="fileForm"
                @opened="checkTermsOfUse"
            >
                <modal-content 
                    close-label="common.close"
                    modal-name="fileForm"
                    :title="components.datasetFiles.modalTitle"
                >
                    <pkp-form style="margin: -1rem" v-bind="components.datasetFileForm" @set="set" @success="fileFormSuccess">
                    </pkp-form>
                </modal-content>
            </modal>
            <pkp-form style="margin: -1rem" v-bind="components.datasetMetadata" @set="set" @success="location.reload()"></pkp-form>
        </div>
    </modal-content>
</modal>