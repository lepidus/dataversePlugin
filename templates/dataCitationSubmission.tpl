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

{if $dataCitation}
<section class="item data_citation">
    <h2 class="label">{translate key="plugins.generic.dataverse.dataCitationLabel"}</h2>
    <span class="value">
        <p>{$dataCitation}</p>
    </span>
</section>
{/if}