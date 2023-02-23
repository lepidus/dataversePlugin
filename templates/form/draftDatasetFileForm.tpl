<script type="text/javascript">
    // Attach the file upload form handler.
    $(function() {ldelim}
        $('#uploadForm').pkpHandler(
            '$.pkp.controllers.form.FileUploadFormHandler',
            {ldelim}
                $uploader: $('#plupload'),
                uploaderOptions: {ldelim}
                uploadUrl: {url|json_encode op="uploadFile" params=$requestArgs escape=false},
                baseUrl: {$baseUrl|json_encode}
                {rdelim}
            {rdelim}
        );
    {rdelim});
</script>

<form class="pkp_form" id="uploadForm" action="{url op="saveFile" params=$requestArgs}" method="post">
    {csrf}

    {include file="controllers/notification/inPlaceNotification.tpl" notificationId="libraryFileUploadNotification"}
    <input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
    <input type="hidden" name="submissionId" value="{$submissionId|escape}" />

    {fbvFormArea id="file"}
        {fbvFormSection title="common.file" required=true}
            {include file="controllers/fileUploadContainer.tpl" id="plupload"}
        {/fbvFormSection}
    {/fbvFormArea}

    {capture assign="termsOfUseDescription"}{translate key="plugins.generic.dataverse.termsOfUse.description" params=$termsOfUseArgs}{/capture}    
    {fbvFormSection label="plugins.generic.dataverse.termsOfUse.label" list=true}
        {fbvElement type="checkbox" name="termsOfUse" id="termsOfUse" checked=false label=$termsOfUseDescription translate=false}
    {/fbvFormSection}

    <p><span class="formRequired">{translate key="common.requiredField"}</span></p>

    {fbvFormButtons}
</form>