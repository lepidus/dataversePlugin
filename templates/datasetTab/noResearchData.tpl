<section class="noResearchData -pkpClearfix">
    <p>
		{translate key="plugins.generic.dataverse.researchData.noResearchData"}
	</p>
    <pkp-button
		v-if="canEditPublication && workingPublication.status !== getConstant('STATUS_PUBLISHED')"
		@click="$modal.show('uploadResearchData')"
	>
        {translate key="plugins.generic.dataverse.researchData.uploadResearchData"}
    </pkp-button>
	<p v-else>
		{translate key="plugins.generic.dataverse.researchData.uploadDisabled"}
	</p>
    <pkp-button
		v-if="canEditPublication && workingPublication.status !== getConstant('STATUS_PUBLISHED')"
		@click="$modal.show('associateResearchData')"
	>
        {translate key="plugins.generic.dataverse.researchData.associate"}
    </pkp-button>
	<modal
		name="uploadResearchData"
		title="{translate key="plugins.generic.dataverse.researchData.uploadResearchData"}"
		:closeLabel="__('common.close')"
	>
		<dataset-files-list-panel
			v-bind="components.datasetFiles"
			@set='set'
		></dataset-files-list-panel>
		<pkp-form 
			style="margin: -1rem" 
			v-bind="components.datasetMetadata" 
			@set="set" 
			@success="location.reload()"
		></pkp-form>
	</modal>
	<modal
		name="associateResearchData"
		title="{translate key="plugins.generic.dataverse.researchData.associate"}"
		:closeLabel="__('common.close')"
	>
	<p>
		{translate key="plugins.generic.dataverse.researchData.associate.disclaimer"}
	</p>
		<pkp-form
			v-bind="components.associateDataset"
			@set='set'
			@success="location.reload()"
		></pkp-form>
	</modal>
	{if $dataverseAdditionalInstructions}
		<div
			v-if="canEditPublication && workingPublication.status !== getConstant('STATUS_PUBLISHED')"
			id="dataverseAdditionalInstructions"
		>
			{$dataverseAdditionalInstructions}
		</div>
	{/if}
</section>
