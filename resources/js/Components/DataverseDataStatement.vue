<template>
	<div class="dataverseDataStatement" data-cy="dataverse-data-statement">
		<div v-if="isLoading" class="dataverseDataStatement__loading">
			<Spinner />
			{{ t('common.loading') }}
		</div>
		<div v-else-if="dataStatementForm" class="-m-5">
			<PkpForm
				v-bind="dataStatementForm"
				data-cy="dataverse-data-statement-form"
				@set="setForm"
				@success="triggerDataChange"
			/>
		</div>
	</div>
</template>

<script setup>
import {ref, watch} from 'vue';

const {useLocalize} = pkp.modules.useLocalize;
const {useFetch} = pkp.modules.useFetch;
const {useUrl} = pkp.modules.useUrl;
const {useDataChanged} = pkp.modules.useDataChanged;

const props = defineProps({
	submission: {type: Object, required: true},
	canEdit: {type: Boolean, default: false},
});

const {t} = useLocalize();

const {apiUrl} = useUrl('dataverse/dataStatement');
const {data, isLoading, fetch} = useFetch(apiUrl, {
	query: {submissionId: props.submission.id},
});

const {triggerDataChange} = useDataChanged(fetch);

const dataStatementForm = ref(null);

watch(data, (newData) => {
	if (!newData) {
		dataStatementForm.value = null;
		return;
	}
	dataStatementForm.value = {...newData.form, canSubmit: props.canEdit};
});

function setForm(id, formData) {
	Object.assign(dataStatementForm.value, formData);
}

fetch();
</script>

<style lang="css">
.dataverseDataStatement__loading {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}
</style>
