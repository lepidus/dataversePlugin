{fbvFormSection id="researchDataNotice" title="plugins.generic.dataverse.researchData"}
    <p>{$researchDataNotice}</p>
{/fbvFormSection}

{if $canPublishResearchData}
    {fbvFormSection label="plugins.generic.dataverse.researchData.wouldLikeToPublish" list=true required=true}
        {fbvElement type="radio" id="userGroup" name="shouldPublishResearchData" label='common.yes' value="1" required=true checked=false}
        {fbvElement type="radio" id="userGroup" name="shouldPublishResearchData" label='common.no' value="0" required=true checked=false}
    {/fbvFormSection}
{/if}