<div class="submissionWizard__reviewPanel" data-cy="dataverse-review-data-statement">
    <div class="submissionWizard__reviewPanel__header">
        <h3 id="review-plugin-dataverse-data-statement">
            {translate key="plugins.generic.dataverse.dataStatement.title"}
        </h3>
        <pkp-button
            aria-describedby="review-plugin-dataverse-data-statement"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id|escape}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div class="submissionWizard__reviewPanel__body">
        <div class="submissionWizard__reviewPanel__item">
            <div class="submissionWizard__reviewPanel__item__value">
                <notification v-if="errors.dataStatement" type="warning">
                    <icon icon="Error" class="h-5 w-5" :inline="true"></icon>
                    {translate key="plugins.generic.dataverse.dataStatement.required"}
                </notification>
                <ul v-else>
                    <li
                        v-for="type in publication.dataStatementTypes"
                        :key="type"
                        v-strip-unsafe-html="dataStatementTypeLabels[type]"
                    ></li>
                </ul>
            </div>
        </div>
        <div
            v-if="publication.dataStatementTypes && publication.dataStatementTypes.includes({$DATA_STATEMENT_TYPE_REPO_AVAILABLE})"
            class="submissionWizard__reviewPanel__item"
        >
            <h4 class="submissionWizard__reviewPanel__item__header">
                {translate key="plugins.generic.dataverse.dataStatement.repoAvailable.urls"}
            </h4>
            <div class="submissionWizard__reviewPanel__item__value">
                <notification v-if="errors.dataStatementUrls" type="warning">
                    <icon icon="Error" class="h-5 w-5" :inline="true"></icon>
                    {{ errors.dataStatementUrls[0] }}
                </notification>
                <ul v-else>
                    <li v-for="url in publication.dataStatementUrls" :key="url">
                        <a :href="url" target="_new">{{ url }}</a>
                    </li>
                </ul>
            </div>
        </div>
        <div
            v-if="publication.dataStatementTypes && publication.dataStatementTypes.includes({$DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE})"
            class="submissionWizard__reviewPanel__item"
        >
            <notification v-if="errors.dataStatementReason" type="warning">
                <icon icon="Error" class="h-5 w-5" :inline="true"></icon>
                {{ errors.dataStatementReason[0] }}
            </notification>
            <div v-else class="submissionWizard__reviewPanel__item__value">
                {foreach from=$locales item=$locale key=$localeKey}
                    <h4 class="submissionWizard__reviewPanel__item__header">
                        {translate key="plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason"} ({$locale})
                    </h4>
                    {{ publication.dataStatementReason.{$localeKey}
                        ? publication.dataStatementReason.{$localeKey}
                        : '{translate key="common.noneProvided"}'
                    }}
                {/foreach}
            </div>
        </div>
    </div>
</div>
