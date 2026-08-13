<template>
	<div ref="root" class="dataverseWizardSection">
		<DatasetFilesListPanel
			v-if="isResearchDataSection"
			v-bind="datasetFiles"
			@set="(...args) => emit('set', ...args)"
		/>
	</div>
</template>

<script setup>
import {computed, onMounted, ref, watch} from 'vue';
import DatasetFilesListPanel from './DatasetFilesListPanel.vue';
import {hasDataStatementType} from '../dataStatementStore';

const props = defineProps({
	section: {type: Object, required: true},
	datasetFiles: {type: Object, default: () => ({})},
});

const emit = defineEmits(['set']);

const CONDITIONAL_SECTIONS = ['datasetFiles', 'datasetMetadata'];

const root = ref(null);

const isResearchDataSection = computed(() => props.section.id === 'datasetFiles');

const isConditionalSection = computed(() =>
	CONDITIONAL_SECTIONS.includes(props.section.id),
);

const isDepositingResearchData = computed(() =>
	hasDataStatementType(pkp.const.DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED),
);

const isDataStatementSection = computed(
	() => props.section.id === 'dataStatement',
);

const needsFormLocales = computed(() =>
	hasDataStatementType(pkp.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE),
);

function updatePanelVisibility() {
	if (!isConditionalSection.value) {
		return;
	}
	const panel = root.value?.closest('.panelSection');
	if (panel) {
		panel.hidden = !isDepositingResearchData.value;
	}
}

function updateFormLocalesVisibility() {
	if (!isDataStatementSection.value) {
		return;
	}
	const locales = root.value
		?.closest('.panelSection')
		?.querySelector('.pkpFormLocales');
	if (locales) {
		locales.hidden = !needsFormLocales.value;
	}
}

function update() {
	updatePanelVisibility();
	updateFormLocalesVisibility();
}

onMounted(update);
watch([isDepositingResearchData, needsFormLocales], update);
</script>
