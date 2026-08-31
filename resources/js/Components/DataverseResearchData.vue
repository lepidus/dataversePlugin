<template>
	<div class="dataverseResearchData" data-cy="dataverse-research-data">
		<div v-if="isLoading" class="dataverseResearchData__loading">
			<Spinner />
			{{ t('plugins.generic.dataverse.metadataForm.loadingDataset') }}
		</div>
		<template v-else-if="state">
			<div
				v-if="state.error"
				class="dataverseResearchData__unavailable"
				data-cy="dataverse-unavailable"
			>
				<p>{{ state.error }}</p>
				<PkpButton @click="load">
					{{ t('plugins.generic.dataverse.error.retry') }}
				</PkpButton>
			</div>
			<template v-else-if="state.hasDataset">
				<PkpHeader>
					<h2>{{ t('plugins.generic.dataverse.researchData') }}</h2>
					<template #actions>
						<PkpButton
							:is-warnable="true"
							:is-disabled="!canEdit || state.datasetIsPublished"
							data-cy="dataverse-delete-dataset"
							@click="openDeleteDatasetModal"
						>
							{{ t('plugins.generic.dataverse.researchData.delete') }}
						</PkpButton>
						<PkpButton
							v-if="canPublish"
							:is-warnable="true"
							data-cy="dataverse-disassociate-dataset"
							@click="openDisassociateDatasetDialog"
						>
							{{ t('plugins.generic.dataverse.researchData.disassociate') }}
						</PkpButton>
						<PkpButton
							v-if="canPublish && !state.datasetIsPublished"
							data-cy="dataverse-publish-dataset"
							@click="openPublishDatasetDialog"
						>
							{{ t('plugins.generic.dataverse.researchData.publish') }}
						</PkpButton>
					</template>
				</PkpHeader>
				<div class="dataverseResearchData__labels">
					<template v-if="!state.datasetIsPublished">
						<PkpBadge color-variant="primary-bg">
							{{ t('plugins.generic.dataverse.researchData.label.draft') }}
						</PkpBadge>
						<PkpBadge color-variant="attention-bg">
							{{ t('plugins.generic.dataverse.researchData.label.unpublished') }}
						</PkpBadge>
					</template>
					<PkpBadge v-if="state.datasetInReview" color-variant="success-bg">
						{{ t('plugins.generic.dataverse.researchData.label.inReview') }}
					</PkpBadge>
				</div>
				<p
					v-strip-unsafe-html="state.citation"
					class="dataverseResearchData__citation"
					data-cy="dataverse-citation"
				/>
				<PkpTabs
					:label="t('plugins.generic.dataverse.researchData')"
					:is-side-tabs="true"
				>
					<PkpTab
						id="datasetMetadata"
						:label="t('plugins.generic.dataverse.researchData.metadata')"
					>
						<PkpForm
							v-bind="metadataForm"
							data-cy="dataverse-metadata-form"
							@set="setMetadataForm"
							@success="triggerDataChange"
						/>
					</PkpTab>
					<PkpTab
						id="datasetFiles"
						:label="t('plugins.generic.dataverse.researchData.files')"
					>
						<DatasetFilesListPanel
							v-bind="filesListPanel"
							@set="setFilesListPanel"
						/>
					</PkpTab>
				</PkpTabs>
			</template>
			<div v-else class="dataverseResearchData__empty">
				<p>{{ t('plugins.generic.dataverse.researchData.noResearchData') }}</p>
				<PkpButtonRow v-if="canDeposit">
					<PkpButton data-cy="dataverse-upload-research-data" @click="openDepositModal">
						{{ t('plugins.generic.dataverse.researchData.uploadResearchData') }}
					</PkpButton>
					<PkpButton data-cy="dataverse-associate-dataset" @click="openAssociateModal">
						{{ t('plugins.generic.dataverse.researchData.associate') }}
					</PkpButton>
				</PkpButtonRow>
				<p v-else>
					{{ t('plugins.generic.dataverse.researchData.uploadDisabled') }}
				</p>
			</div>
			<div
				v-if="showAdditionalInstructions"
				v-strip-unsafe-html="state.additionalInstructions"
				class="dataverseResearchData__instructions semantic-defaults"
				data-cy="dataverse-additional-instructions"
			/>
		</template>
	</div>
</template>

<script setup>
import {computed, ref, watch} from 'vue';
import DatasetFilesListPanel from './DatasetFilesListPanel.vue';
import DatasetDepositModal from './DatasetDepositModal.vue';
import DatasetAssociateModal from './DatasetAssociateModal.vue';
import DatasetDeleteModal from './DatasetDeleteModal.vue';

const {useLocalize} = pkp.modules.useLocalize;
const {useModal} = pkp.modules.useModal;
const {useFetch} = pkp.modules.useFetch;
const {useUrl} = pkp.modules.useUrl;
const {useDataChanged} = pkp.modules.useDataChanged;

const props = defineProps({
	submission: {type: Object, required: true},
	publication: {type: Object, required: true},
	canEdit: {type: Boolean, default: false},
	canPublish: {type: Boolean, default: false},
});

const {t} = useLocalize();
const {openDialog, openSideModal, closeSideModal} = useModal();

const {triggerDataChange} = useDataChanged(load);

const {apiUrl} = useUrl('dataverse/researchData');
const {
	data: state,
	isLoading,
	fetch: fetchState,
} = useFetch(apiUrl, {query: {submissionId: props.submission.id}});

const metadataForm = ref(null);
const filesListPanel = ref(null);

const actionUrl = ref('');
const {fetch: sendPut} = useFetch(actionUrl, {method: 'PUT'});
const {fetch: sendDelete} = useFetch(actionUrl, {method: 'DELETE'});

const canDeposit = computed(
	() => props.canEdit && props.publication.status !== pkp.const.STATUS_PUBLISHED,
);

const showAdditionalInstructions = computed(
	() =>
		state.value?.additionalInstructions &&
		!state.value.error &&
		(state.value.hasDataset || canDeposit.value),
);

watch(state, (newState) => {
	if (!newState || newState.error) {
		metadataForm.value = null;
		filesListPanel.value = null;
		return;
	}

	const canChange = props.canEdit && !newState.datasetIsPublished;

	metadataForm.value = {...newState.forms.datasetMetadata, canSubmit: canChange};
	filesListPanel.value = {...newState.filesListPanel, canChangeFiles: canChange};
});

function setMetadataForm(id, data) {
	Object.assign(metadataForm.value, data);
}

function setFilesListPanel(id, data) {
	Object.assign(filesListPanel.value, data);
}

async function load() {
	await fetchState();
}

function openDepositModal() {
	openSideModal(DatasetDepositModal, {
		title: t('plugins.generic.dataverse.researchData.uploadResearchData'),
		form: {...state.value.forms.datasetMetadata},
		filesListPanel: {...state.value.filesListPanel},
		onClose: () => {
			closeSideModal(DatasetDepositModal);
			triggerDataChange();
		},
	});
}

function openAssociateModal() {
	openSideModal(DatasetAssociateModal, {
		title: t('plugins.generic.dataverse.researchData.associate'),
		form: {...state.value.forms.associateDataset},
		onClose: () => {
			closeSideModal(DatasetAssociateModal);
			triggerDataChange();
		},
	});
}

function openDeleteDatasetModal() {
	if (state.value.canSendDeleteEmail) {
		openSideModal(DatasetDeleteModal, {
			title: t('plugins.generic.dataverse.researchData.delete'),
			form: {...state.value.forms.deleteDataset},
			onClose: () => {
				closeSideModal(DatasetDeleteModal);
				triggerDataChange();
			},
		});
		return;
	}

	confirmDatasetAction({
		title: t('plugins.generic.dataverse.researchData.delete'),
		message: t('plugins.generic.dataverse.modal.confirmDatasetDelete'),
		label: t('plugins.generic.dataverse.researchData.delete'),
		isWarnable: true,
		url: state.value.datasetUrl,
		method: 'DELETE',
	});
}

function openDisassociateDatasetDialog() {
	confirmDatasetAction({
		title: t('plugins.generic.dataverse.researchData.disassociate'),
		message: t('plugins.generic.dataverse.researchData.disassociate.description'),
		label: t('plugins.generic.dataverse.researchData.disassociate'),
		isWarnable: true,
		url: state.value.datasetUrl + '/disassociate',
		method: 'PUT',
	});
}

function openPublishDatasetDialog() {
	confirmDatasetAction({
		title: t('plugins.generic.dataverse.researchData.publish'),
		message: state.value.publishConfirmMessage,
		label: t('common.yes'),
		isWarnable: false,
		url: state.value.datasetUrl + '/publish',
		method: 'PUT',
	});
}

function confirmDatasetAction({title, message, label, isWarnable, url, method}) {
	openDialog({
		title,
		message,
		actions: [
			{
				label,
				isWarnable,
				callback: async (close) => {
					close();
					actionUrl.value = url;
					await (method === 'DELETE' ? sendDelete() : sendPut());
					await triggerDataChange();
				},
			},
			{
				label: t('common.cancel'),
				callback: (close) => close(),
			},
		],
	});
}

load();
</script>

<style lang="css">
.dataverseResearchData__loading {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}

.dataverseResearchData__unavailable > button,
.dataverseResearchData__empty > * + * {
	margin-top: 1rem;
}

.dataverseResearchData__labels {
	display: flex;
	gap: 0.25rem;
	margin: 0.5rem 0;
}

.dataverseResearchData__citation {
	margin-bottom: 1rem;
}

.dataverseResearchData__instructions {
	margin-top: 1.5rem;
	font-size: 0.875rem;
	line-height: 1.5rem;
	text-align: justify;
}
</style>
