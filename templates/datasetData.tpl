{**
 * templates/datasetData.tpl
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Dataverse dataset data
 *
 *}

<section class="item datasetData -pkpClearfix" id="datasetData">
    <pkp-header>
        <h1>{translate key="plugins.generic.dataverse.researchData"}</h1>
        <template slot="actions">
            <pkp-button @click="$.pkp.plugins.generic.dataverse.openDeleteDatasetModal" class="pkpButton--isWarnable">
                {translate key="plugins.generic.dataverse.researchData.delete"}
            </pkp-button>
        </template>
    </pkp-header>
    <span class="value">
        <p></p>
    </span>
    <tabs label="Dataset data">
        <tab id="dataset_metadata" label={translate key="plugins.generic.dataverse.researchData.metadata"}>
            <pkp-form v-bind="components.datasetMetadata" @set="set"></pkp-form>
        </tab>
        <tab id="dataset_files" label={translate key="plugins.generic.dataverse.researchData.files"}>
            <div class="filesList -pkpClearfix">
                <list-panel v-bind="components.datasetFiles" @set="set">
                    <pkp-header slot="header">
                        <h2>{translate key="plugins.generic.dataverse.researchData"}</h2>
                        <spinner v-if="components.datasetFiles.isLoading"></spinner>
                        <template slot="actions">
                            <pkp-button ref="datasetFileModalButton"
                                @click="$.pkp.plugins.generic.dataverse.datasetFileModalOpen">
                                {translate key="plugins.generic.dataverse.addResearchData"}
                            </pkp-button>
                        </template>
                    </pkp-header>
                    <template v-slot:item="item">
                        <div class="listPanel__itemSummary">
                            <div class="listPanel__itemIdentity">
                                <div class="listPanel__itemTitle">
                                    <a :href="$.pkp.plugins.generic.dataverse.getFileDownloadUrl(item.item)">
                                        {{ item.item.title }}
                                    </a>
                                </div>
                            </div>
                            <div class="listPanel__itemActions">
                                <pkp-button @click="$.pkp.plugins.generic.dataverse.openDeleteModal(item.item.id)"
                                    class="pkpButton--isWarnable">
                                    {{ __('common.delete') }}
                                </pkp-button>
                            </div>
                        </div>
                    </template>
                </list-panel>
                <modal v-bind="MODAL_PROPS" name="datasetFileModal"
                    @opened="$.pkp.plugins.generic.dataverse.defineTermsOfUseErrors"
                    @closed="setFocusToRef('datasetFileModalButton')">
                    <modal-content close-label="common.close" modal-name="datasetFileModal"
                        title="{translate key="plugins.generic.dataverse.modal.addFile.title"}">
                        <pkp-form style="margin: -1rem" v-bind="components.datasetFileForm" @set="set"
                            @success="$.pkp.plugins.generic.dataverse.formSuccess">
                        </pkp-form>
                    </modal-content>
                </modal>
            </div>
        </tab>
    </tabs>
</section>