import DatasetFilesListPanel from './Components/DatasetFilesListPanel.vue';
import DataverseWizardSection from './Components/DataverseWizardSection.vue';
import FieldControlledVocabUrl from './Components/FieldControlledVocabUrl.vue';
import FieldDataStatementReason from './Components/FieldDataStatementReason.vue';
import FieldDataStatementTypes from './Components/FieldDataStatementTypes.vue';

pkp.registry.registerComponent('DataverseWizardSection', DataverseWizardSection);
pkp.registry.registerComponent('DatasetFilesListPanel', DatasetFilesListPanel);

pkp.registry.registerComponent('field-data-statement-types', FieldDataStatementTypes);
pkp.registry.registerComponent('field-data-statement-reason', FieldDataStatementReason);
pkp.registry.registerComponent('field-controlled-vocab-url', FieldControlledVocabUrl);
