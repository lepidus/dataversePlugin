<section class="noResearchData -pkpClearfix">
    <p>
		{translate key="plugins.generic.dataverse.researchData.noResearchData"}
	</p>
    <pkp-button
		v-if="submission.status !== getConstant('STATUS_PUBLISHED')"
		@click="$modal.show('uploadResearchData')"
	>
        {translate key="plugins.generic.dataverse.researchData.uploadResearchData"}
    </pkp-button>
	{if !$canPublish}
		<p v-else>
			{translate key="plugins.generic.dataverse.researchData.uploadDisabled"}
		</p>
	{/if}
    <modal
		name="uploadResearchData"
		title="{translate key="plugins.generic.dataverse.researchData.uploadResearchData"}"
		:closeLabel="__('common.close')"
	>
		<dataset-files-list-panel
			v-bind="components.datasetFiles"
			@set='set'
		></dataset-files-list-panel>
		<pkp-form style="margin: -1rem" v-bind="components.datasetMetadata" @set="set" @success="location.reload()"></pkp-form>
	</modal>
</section>
