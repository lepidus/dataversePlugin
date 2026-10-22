<template>
	<PkpSideModalBody>
		<template #title>
			{{ title }}
		</template>
		<PkpSideModalLayoutBasic>
			<PkpForm
				v-bind="form"
				data-cy="dataverse-add-file-form"
				@set="(...args) => emit('updateForm', ...args)"
				@success="(...args) => emit('formSuccess', ...args)"
			/>
		</PkpSideModalLayoutBasic>
	</PkpSideModalBody>
</template>

<script setup>
defineProps({
	title: {type: String, required: true},
	form: {type: Object, required: true},
});

const emit = defineEmits(['updateForm', 'formSuccess']);
</script>
