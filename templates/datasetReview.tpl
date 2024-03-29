{fbvFormSection label="plugins.generic.dataverse.dataStatement.title"}
    {if $publication->getData('dataStatementTypes')}
        <ul class="data_statement_list">
            {foreach from=$publication->getData('dataStatementTypes') item=type}
                <li>
                    <p>{$allDataStatementTypes[$type]}
                        {if $type === $smarty.const.DATA_STATEMENT_TYPE_REPO_AVAILABLE}
                            <ul>
                                {foreach from=$publication->getData('dataStatementUrls') item=url}
                                    <li>
                                        <a href="{$url|escape}" target="_new">{$url|escape}</a>
                                    </li>
                                {/foreach}
                            </ul>
                        {else if $type === $smarty.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE}
                            <ul>
                                <li>{$publication->getLocalizedData('dataStatementReason')|escape}</li>
                            </ul>
                        {/if}
                    </p>
                </li>
            {/foreach}
        </ul>
    {/if}
{/fbvFormSection}
{if !$restrictReviewerFileAccess}    
    {capture assign=datasetReviewGridHandler}
        {url 
            router=$smarty.const.ROUTE_COMPONENT 
            component="plugins.generic.dataverse.controllers.grid.DatasetReviewGridHandler" 
            op="fetchGrid"
            submissionId=$submission->getId() stageId=$reviewAssignment->getStageId() reviewRoundId=$reviewRoundId reviewAssignmentId=$reviewAssignment->getId() escape=false
        }
    {/capture}
    {load_url_in_div id="datasetReviewGridContainer"|uniqid url=$datasetReviewGridHandler}
{/if}
