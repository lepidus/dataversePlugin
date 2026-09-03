<template>
	<PkpSideModalBody>
		<template #title>
			{{ title }}
		</template>
		<PkpSideModalLayoutBasic>
			<DatasetFilesListPanel
				v-bind="listPanel"
				@set="setListPanel"
			/>
			<PkpForm
				v-bind="metadataForm"
				data-cy="dataverse-deposit-form"
				@set="setForm"
				@success="emit('close')"
			/>
		</PkpSideModalLayoutBasic>
	</PkpSideModalBody>
</template>

<script setup>
import {ref} from 'vue';
import DatasetFilesListPanel from './DatasetFilesListPanel.vue';

const props = defineProps({
	title: {type: String, required: true},
	form: {type: Object, required: true},
	filesListPanel: {type: Object, required: true},
});

const emit = defineEmits(['close']);

const metadataForm = ref({...props.form});
const listPanel = ref({...props.filesListPanel, canChangeFiles: true});

function setForm(id, data) {
	Object.assign(metadataForm.value, data);
}

function setListPanel(id, data) {
	Object.assign(listPanel.value, data);
}
</script>
