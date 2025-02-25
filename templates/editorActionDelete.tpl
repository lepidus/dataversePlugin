<div style="margin-top: 2rem; margin-bottom: 1.5rem;">
{fbvFormSection id="researchDataNotice" title="plugins.generic.dataverse.researchData"}
    {translate key="plugins.generic.dataverse.researchData.deleteNotice" persistentUri=$persistenUri}
{/fbvFormSection}
</div>
{fbvFormSection id="researchDataDeleteChoice" label="plugins.generic.dataverse.researchData.wouldLikeToDelete" list=true required=true}
    {fbvElement type="radio" id="userGroup" name="shouldDeleteResearchData" label='common.yes' value="1" required=true checked=false}
    {fbvElement type="radio" id="userGroup" name="shouldDeleteResearchData" label='common.no' value="0" required=true checked=false}
{/fbvFormSection}