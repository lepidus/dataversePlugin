{capture assign=uploadDatasetFormUrl}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.dataverse.handlers.UploadDatasetHandler" op="uploadDatasetForm" submissionId=$submissionId  escape=false}{/capture}
{load_url_in_div class="teste" id="uploadDatasetModalContainer"|uniqid url=$uploadDatasetFormUrl}
