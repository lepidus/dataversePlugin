<div class="submissionWizard__reviewPanel">
    <div class="submissionWizard__reviewPanel__header">
        <h3 id="review-plugin-draft-dataset-files">
            {translate key="plugins.generic.dataverse.researchData"}
        </h3>
        <pkp-button
            aria-describedby="review-plugin-draft-dataset-files"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div class="submissionWizard__reviewPanel__body">
        <notification
            v-for="(error, i) in errors.datasetFiles"
            :key="i"
            type="warning"
        >
            <icon icon="exclamation-triangle" :inline="true"></icon>
            {{ error }}
        </notification>
        <ul class="submissionWizard__reviewPanel__list">
            <li
                v-for="datasetFile in components.datasetFiles.items"
                :key="datasetFile.id"
                class="submissionWizard__reviewPanel__item__value"
            >
                <a 
                    :href="datasetFile.downloadUrl"
                    class="submissionWizard__reviewPanel__fileLink"
                >
                    {{ datasetFile.fileName}}
                </a>
            </li>
        </ul>
    </div>
</div>