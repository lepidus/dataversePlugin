<div
    v-if="publication.dataStatementTypes && publication.dataStatementTypes.includes(pkp.const.DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED)"
    class="submissionWizard__reviewPanel"
>
    <div class="submissionWizard__reviewPanel__header">
        <h3 id="review-plugin-dataverse-dataset-metadata">
            {translate key="plugins.generic.dataverse.datasetMetadata"}
        </h3>
        <pkp-button
            aria-describedby="review-plugin-dataverse-dataset-metadata"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div class="submissionWizard__reviewPanel__body">
        <div class="submissionWizard__reviewPanel__item">
            <h4 class="submissionWizard__reviewPanel__item__header">
                {translate key="plugins.generic.dataverse.metadataForm.subject.label"}
            </h4>
            <div class="submissionWizard__reviewPanel__item__value">
                <notification v-if="errors.datasetSubject" type="warning">
                    <icon icon="exclamation-triangle" :inline="true"></icon>
                    {translate key="plugins.generic.dataverse.error.datasetSubjectRequired"}
                </notification>
                <template v-else>
                    {{ submission.datasetSubject }}
                </template>
            </div>
        </div>
    </div>
    <div class="submissionWizard__reviewPanel__body">
        <div class="submissionWizard__reviewPanel__item">
            <h4 class="submissionWizard__reviewPanel__item__header">
                {translate key="plugins.generic.dataverse.metadataForm.license.label"}
            </h4>
            <div class="submissionWizard__reviewPanel__item__value">
                <template v-else>
                    {{ submission.datasetLicense }}
                </template>
            </div>
        </div>
    </div>
</div>