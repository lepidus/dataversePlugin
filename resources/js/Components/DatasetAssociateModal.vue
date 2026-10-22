<template>
	<PkpSideModalBody>
		<template #title>
			{{ title }}
		</template>
		<PkpSideModalLayoutBasic>
			<p>
				{{ t('plugins.generic.dataverse.researchData.associate.disclaimer') }}
			</p>
			<PkpForm
				v-bind="associateForm"
				data-cy="dataverse-associate-form"
				@set="setForm"
				@success="emit('close')"
			/>
		</PkpSideModalLayoutBasic>
	</PkpSideModalBody>
</template>

<script setup>
import {ref} from 'vue';

const {useLocalize} = pkp.modules.useLocalize;

const props = defineProps({
	title: {type: String, required: true},
	form: {type: Object, required: true},
});

const emit = defineEmits(['close']);

const {t} = useLocalize();

const associateForm = ref({...props.form});

function setForm(id, data) {
	Object.assign(associateForm.value, data);
}
</script>
