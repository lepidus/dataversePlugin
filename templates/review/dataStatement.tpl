<div class="submissionWizard__reviewPanel">
    <div class="submissionWizard__reviewPanel__header">
        <h3 id="review-plugin-dataverse-data-statement">
            {translate key="plugins.generic.dataverse.dataStatement.title"}
        </h3>
        <pkp-button
            aria-describedby="review-plugin-dataverse-data-statement"
            class="submissionWizard__reviewPanel__edit"
            @click="openStep('{$step.id}')"
        >
            {translate key="common.edit"}
        </pkp-button>
    </div>
    <div class="submissionWizard__reviewPanel__body">
        <div class="submissionWizard__reviewPanel__item">
            <div class="submissionWizard__reviewPanel__item__value">
                <notification v-if="errors.dataStatement" type="warning">
                    <icon icon="exclamation-triangle" :inline="true"></icon>
                    {translate key="plugins.generic.dataverse.dataStatement.required"}
                </notification>
                <div v-if="!errors.dataStatement">
                    {{ publication.dataStatementTypes }}
                </div>
            </div>
        </div>
        {* apenas exibir esse item se tiver DATA_STATEMENT_TYPE_REPO_AVAILABLE no dataStatementTypes *}
        <div class="submissionWizard__reviewPanel__item">
            <h4 class="submissionWizard__reviewPanel__item__header">
                {translate key="plugins.generic.dataverse.dataStatement.repoAvailable.urls"}
            </h4>
            <div class="submissionWizard__reviewPanel__item__value">
                <notification v-if="errors.dataStatementUrls" type="warning">
                    <icon icon="exclamation-triangle" :inline="true"></icon>
                    {{ errors.dataStatementUrls }}
                </notification>
                <div v-if="!errors.dataStatementUrls">
                    <ul>
                        <li v-for="url in publication.dataStatementUrls">
                            <a :href="url" target="_new">{{ url }}</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        {* apenas exibir esse item se tiver DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE no dataStatementTypes *}
        <div class="submissionWizard__reviewPanel__item">
            {foreach from=$locales item=$locale key=$localeKey}
                <h4 class="submissionWizard__reviewPanel__item__header">
                    {translate key="plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason"} ({$locale})
                </h4>
                <div class="submissionWizard__reviewPanel__item__value">
                    <notification v-if="errors.dataStatementReason" type="warning">
                        <icon icon="exclamation-triangle" :inline="true"></icon>
                        {{ errors.dataStatementReason }}
                    </notification>
                    <div v-if="!errors.dataStatementReason">
                        {{ publication.dataStatementReason.{$localeKey} }}
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>