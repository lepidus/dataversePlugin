<div id="sendDataset" class="pkp_controllers_grid">
    <div class="header">
        <h4>{translate key="plugins.generic.dataverse.dataCitationLabel"}</h4>
        {capture assign=datasetGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.dataverse.handlers.UploadDatasetHandler" op="uploadDataset" submissionId=$submissionId escape=false}{/capture}
        {load_url_in_div class="actions" id="datasetGridContainer"|uniqid url=$datasetGridUrl}
    </div>
</div>
<br>