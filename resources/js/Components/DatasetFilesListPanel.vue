<template>
	<div class="datasetFilesListPanel" data-cy="dataverse-research-data">
		<PkpListPanel :items="currentItems">
			<template #header>
				<PkpHeader>
					<h2>{{ title }}</h2>
					<Spinner v-if="isLoading" />
					<template #actions>
						<PkpButton
							:is-disabled="isLoading || !canChangeFiles"
							data-cy="dataverse-add-file"
							@click="openAddFileModal"
						>
							{{ addFileLabel }}
						</PkpButton>
					</template>
				</PkpHeader>
			</template>
			<template #item-title="{item}">
				<a
					:id="'datasetFile-' + item.id"
					:href="item.downloadUrl"
					data-cy="dataverse-file-name"
				>
					{{ item.fileName }}
				</a>
			</template>
			<template #item-actions="{item}">
				<PkpButton
					:is-disabled="isLoading || !canChangeFiles"
					:is-warnable="true"
					:aria-describedby="'datasetFile-' + item.id"
					data-cy="dataverse-delete-file"
					@click="openDeleteFileModal(item)"
				>
					{{ t('common.delete') }}
				</PkpButton>
			</template>
		</PkpListPanel>
		<div
			v-if="additionalInstructions"
			v-strip-unsafe-html="additionalInstructions"
			class="datasetFilesListPanel__instructions semantic-defaults"
			data-cy="dataverse-additional-instructions"
		/>
	</div>
</template>

<script setup>
import {computed, ref} from 'vue';
import DatasetFileAddModal from './DatasetFileAddModal.vue';

const {useLocalize} = pkp.modules.useLocalize;
const {useModal} = pkp.modules.useModal;
const {useFetch} = pkp.modules.useFetch;

const props = defineProps({
	id: {type: String, required: true},
	title: {type: String, default: ''},
	addFileLabel: {type: String, default: ''},
	addFileModalTitle: {type: String, default: ''},
	fileListUrl: {type: String, required: true},
	fileActionUrl: {type: String, required: true},
	form: {type: Object, required: true},
	items: {type: Array, default: () => []},
	canChangeFiles: {type: Boolean, default: true},
	additionalInstructions: {type: String, default: ''},
	deleteFileTitle: {type: String, default: ''},
	deleteFileMessage: {type: String, default: ''},
	deleteFileConfirmLabel: {type: String, default: ''},
});

const emit = defineEmits(['set']);

const {t} = useLocalize();
const {openSideModal, closeSideModal, openDialog} = useModal();

const activeForm = ref({...props.form});
const refreshedItems = ref(null);

const currentItems = computed(() => refreshedItems.value ?? props.items);

const {
	data: fileList,
	isLoading: isListLoading,
	fetch: fetchFileList,
} = useFetch(props.fileListUrl);

const deleteQuery = ref({});
const {isLoading: isDeleting, fetch: sendDelete} = useFetch(props.fileActionUrl, {
	method: 'DELETE',
	query: deleteQuery,
});

const isLoading = computed(() => isListLoading.value || isDeleting.value);

async function refreshItems() {
	await fetchFileList();
	if (!fileList.value) {
		return;
	}
	refreshedItems.value = fileList.value.items;
	emit('set', props.id, {items: refreshedItems.value});
}

function openAddFileModal() {
	activeForm.value = {...props.form};

	openSideModal(DatasetFileAddModal, {
		title: props.addFileModalTitle,
		form: activeForm.value,
		onUpdateForm: (formId, data) => {
			activeForm.value = {...activeForm.value, ...data};
		},
		onFormSuccess: () => {
			closeSideModal(DatasetFileAddModal);
			refreshItems();
		},
	});
}

function openDeleteFileModal(item) {
	openDialog({
		name: 'deleteDatasetFile',
		title: props.deleteFileTitle,
		message: props.deleteFileMessage.replace('{$title}', item.fileName),
		actions: [
			{
				label: props.deleteFileConfirmLabel,
				isWarnable: true,
				callback: async (close) => {
					close();
					deleteQuery.value = {fileId: item.id, fileName: item.fileName};
					await sendDelete();
					await refreshItems();
				},
			},
			{
				label: t('common.cancel'),
				callback: (close) => close(),
			},
		],
	});
}
</script>

<style lang="css">
.datasetFilesListPanel__instructions {
	margin-top: 1.5rem;
	font-size: 0.875rem;
	line-height: 1.5rem;
	text-align: justify;
}
</style>
