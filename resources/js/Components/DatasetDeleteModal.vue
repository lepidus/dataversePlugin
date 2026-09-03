<template>
	<PkpSideModalBody>
		<template #title>
			{{ title }}
		</template>
		<PkpSideModalLayoutBasic>
			<PkpForm
				v-bind="deleteForm"
				data-cy="dataverse-delete-dataset-form"
				@set="setForm"
				@success="emit('close')"
			/>
		</PkpSideModalLayoutBasic>
	</PkpSideModalBody>
</template>

<script setup>
import {ref} from 'vue';

const props = defineProps({
	title: {type: String, required: true},
	form: {type: Object, required: true},
});

const emit = defineEmits(['close']);

const deleteForm = ref({...props.form});

function setForm(id, data) {
	Object.assign(deleteForm.value, data);
}
</script>
