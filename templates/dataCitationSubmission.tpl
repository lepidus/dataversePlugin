{**
 * templates/dataCitationSubmission.tpl
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Dataverse submission data citation
 *
 *}

<section class="item data_citation" id="data_citation">
    <h2 class="label">{translate key="plugins.generic.dataverse.researchData"}</h2>
    <span class="value">
        <p></p>
    </span>
    <tabs label="Dataset data" :is-side-tabs="false">
        <tab id="metadata" label={translate key="plugins.generic.dataverse.researchData.metadata"}>
            <pkp-form
                v-bind="components.{$smarty.const.FORM_DATASET_METADATA}"
                @set="set"
            ></pkp-form>
    </tabs>
</section>