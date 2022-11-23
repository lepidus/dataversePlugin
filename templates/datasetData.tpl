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

<section class="item data_citation" id="data_citation">
    <h2 class="label">{translate key="plugins.generic.dataverse.researchData"}</h2>
    <span class="value">
        <p></p>
    </span>
    <tabs label="Dataset data">
        <tab id="dataset_metadata" label={translate key="plugins.generic.dataverse.researchData.metadata"}>
            <pkp-form
                v-bind="components.datasetMetadata"
                @set="set"
            ></pkp-form>
        </tab>
        <tab id="dataset_files" label={translate key="plugins.generic.dataverse.researchData.files"}>
            <list-panel 
                v-bind="components.datasetFiles"
				@set="set"
            >
                <pkp-header slot="header">
                    <h2>{translate key="plugins.generic.dataverse.researchData"}</h2>
                    <template slot="actions">
                        <pkp-button ref="datasetFileModalButton" @click="$modal.show('datasetFileModal')">
                            {translate key="plugins.generic.dataverse.datasetButton"}
                        </pkp-button>
                    </template>
                </pkp-header>
            </list-panel>
            <modal
                v-bind="MODAL_PROPS"
                name="datasetFileModal"
                @closed="setFocusToRef('datasetFileModalButton')"
            >
                <modal-content
                    close-label="common.close"
                    modal-name="datasetFileModal"
                    title="{translate key="plugins.generic.dataverse.modal.addFile.title"}"
                >
                    <pkp-form style="margin: -1rem"
                        v-bind="components.datasetFileForm"
                        @set="set"
                    >
                    </pkp-form>
                </modal-content>
            </modal>
        </tab>
    </tabs>
</section>