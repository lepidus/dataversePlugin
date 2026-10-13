<template>
	<div class="flex flex-col gap-y-2" data-cy="dataverse-dataset-header">
		<p
			v-strip-unsafe-html="state.citation"
			class="text-lg-normal"
			data-cy="dataverse-citation"
		/>
		<div
			v-if="!state.datasetIsPublished || state.datasetInReview"
			class="flex flex-wrap items-center gap-x-4 gap-y-2"
		>
			<span class="text-lg-bold">
				{{ t('semicolon', {label: t('common.status')}) }}
			</span>
			<template v-if="!state.datasetIsPublished">
				<span class="flex items-center" data-cy="dataverse-status-draft">
					<span class="h-[1em] w-[1em] rounded-full bg-primary" aria-hidden="true" />
					<span class="ms-1 text-base-normal">
						{{ t('plugins.generic.dataverse.researchData.label.draft') }}
					</span>
				</span>
				<span class="flex items-center" data-cy="dataverse-status-unpublished">
					<span class="h-[1em] w-[1em] rounded-full bg-attention" aria-hidden="true" />
					<span class="ms-1 text-base-normal">
						{{ t('plugins.generic.dataverse.researchData.label.unpublished') }}
					</span>
				</span>
			</template>
			<span v-if="state.datasetInReview" class="flex items-center" data-cy="dataverse-status-in-review">
				<span class="h-[1em] w-[1em] rounded-full bg-success" aria-hidden="true" />
				<span class="ms-1 text-base-normal">
					{{ t('plugins.generic.dataverse.researchData.label.inReview') }}
				</span>
			</span>
		</div>
	</div>
</template>

<script setup>
defineProps({state: {type: Object, required: true}});
const {useLocalize} = pkp.modules.useLocalize;
const {t} = useLocalize();
</script>
