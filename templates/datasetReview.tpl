{if !$restrictReviewerFileAccess}
    {capture assign=datasetDataReviewGridHandler}
        {url 
            router=$smarty.const.ROUTE_COMPONENT 
            component="plugins.generic.dataverse.controllers.grid.DatasetReviewGridHandler" 
            op="fetchGrid"
            submissionId=$submission->getId() stageId=$reviewAssignment->getStageId() reviewRoundId=$reviewRoundId reviewAssignmentId=$reviewAssignment->getId() escape=false
        }
    {/capture}
    {load_url_in_div id="datasetReviewGridContainer"|uniqid url=$datasetDataReviewGridHandler}
{/if}
