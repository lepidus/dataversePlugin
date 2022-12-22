<div id="draftDatasetFilesContainer" style="margin-bottom: 1rem">
	<list-panel :items="components.draftDatasetFilesList.items">
        <pkp-header slot="header">
            <h2>{translate key="plugins.generic.dataverse.researchData"}</h2>
			<spinner v-if="isLoading"></spinner>
            <template slot="actions">
                <pkp-button ref="datasetModalButton" @click="datasetFileModalOpen">
                    {translate key="plugins.generic.dataverse.datasetButton"}
                </pkp-button>
            </template>
        </pkp-header>
		<template v-slot:item="item">
			<div class="listPanel__itemSummary">
				<div class="listPanel__itemIdentity">
					<div class="listPanel__itemTitle">
						{{ item.item.fileName }}
					</div>
				</div>
				<div class="listPanel__itemActions">
					<pkp-button @click="openDeleteModal(item.item.id)" class="pkpButton--isWarnable">
						{{ __('common.delete') }}
					</pkp-button>
				</div>
			</div>
		</template>
    </list-panel>
    <modal
		v-bind="MODAL_PROPS"
		name="datasetModal"
		@opened="checkTermsOfUse"
		@closed="datasetFileModalClose"
	>
		<modal-content
			close-label="common.close"
			modal-name="datasetModal"
			title="{translate key="plugins.generic.dataverse.modal.addFile.title"}"
		>
            <pkp-form 
				v-bind="components.draftDatasetFileForm"
				:errors="errors"
				@set="set"
				@success="formSuccess"
			>
			</pkp-form>
		</modal-content>
	</modal>
	{capture assign=addGalleyLabel}{translate key="submission.layout.galleys"}{/capture}
	<label class="description">{translate key="plugins.generic.dataverse.researchDataDescription" addGalleyLabel=$addGalleyLabel}</label>
    <script type="text/javascript">
        pkp.registry.init('draftDatasetFilesContainer', 'DraftDatasetFilesPage', {$state|json_encode});
    </script>

</div>