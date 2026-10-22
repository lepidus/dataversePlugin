<div
    v-if="publication.dataStatementTypes && publication.dataStatementTypes.includes({$DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED})"
    class="submissionWizard__reviewPanel"
    data-cy="dataverse-review-dataset-metadata"
>
    <div class="submissionWizard__reviewPanel__header">
        <h3 id="review-plugin-dataverse-dataset-metadata">
            {translate key="plugins.generic.dataverse.datasetMetadata"}
        </h3>
        <pkp-button
            aria-describedby="review-plugin-dataverse-dataset-metadata"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id|escape}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div class="submissionWizard__reviewPanel__body">
        <div class="submissionWizard__reviewPanel__item">
            <h4 class="submissionWizard__reviewPanel__item__header">
                {translate key="plugins.generic.dataverse.metadataForm.language.label"}
            </h4>
            <div class="submissionWizard__reviewPanel__item__value">
                {{ submission.datasetLanguage }}
            </div>
        </div>
        <div class="submissionWizard__reviewPanel__item">
            <h4 class="submissionWizard__reviewPanel__item__header">
                {translate key="plugins.generic.dataverse.metadataForm.subject.label"}
            </h4>
            <div class="submissionWizard__reviewPanel__item__value">
                <notification v-if="errors.datasetSubject" type="warning">
                    <icon icon="Error" class="h-5 w-5" :inline="true"></icon>
                    {translate key="plugins.generic.dataverse.error.datasetSubject.required"}
                </notification>
                <template v-else>
                    {{ submission.datasetSubject }}
                </template>
            </div>
        </div>
        <div class="submissionWizard__reviewPanel__item">
            <h4 class="submissionWizard__reviewPanel__item__header">
                {translate key="plugins.generic.dataverse.metadataForm.license.label"}
            </h4>
            <div class="submissionWizard__reviewPanel__item__value">
                {{ submission.datasetLicense }}
            </div>
        </div>
        <div class="submissionWizard__reviewPanel__item">
            <h4 class="submissionWizard__reviewPanel__item__header">
                {translate key="plugins.generic.dataverse.metadataForm.relationType.label"}
            </h4>
            <div class="submissionWizard__reviewPanel__item__value">
                <template v-if="submission.datasetRelationType">
                    {{ relationTypes[submission.datasetRelationType]['label'] }}
                </template>
            </div>
        </div>
        {foreach from=$requiredMetadataFields item=field}
            {assign var=metadataName value=$field.submissionProp}
            <div class="submissionWizard__reviewPanel__item">
                <template v-if="errors.{$metadataName|escape}">
                    <notification
                        v-for="(error, i) in errors.{$metadataName|escape}"
                        :key="i"
                        type="warning"
                    >
                        <icon icon="Error" class="h-5 w-5" :inline="true"></icon>
                        {{ error }}
                    </notification>
                </template>
                <h4 class="submissionWizard__reviewPanel__item__header">
                    {$field.displayName|escape}
                </h4>
                <div class="submissionWizard__reviewPanel__item__value">
                    <template v-if="submission.{$metadataName|escape}">
                        {{ submission.{$metadataName|escape} }}
                    </template>
                    <template v-else>
                        {translate key="common.noneProvided"}
                    </template>
                </div>
            </div>
        {/foreach}
    </div>
</div>
