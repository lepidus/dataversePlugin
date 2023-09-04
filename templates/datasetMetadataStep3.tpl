{fbvFormSection 
    label="plugins.generic.dataverse.metadataForm.subject.label" 
    description="plugins.generic.dataverse.metadataForm.subject.description" 
    required=true
}
    {fbvElement 
        type="select" 
        name="datasetSubject" 
        id="datasetSubject" 
        defaultLabel="" 
        defaultValue=""
        selected=$subjectId 
        from=$dataverseSubjectVocab 
        translate=false 
        required=true
    }
{/fbvFormSection}

{fbvFormSection 
    label="plugins.generic.dataverse.metadataForm.license.label" 
    description="plugins.generic.dataverse.metadataForm.license.description" 
    required=true
}
    {fbvElement 
        type="select" 
        name="datasetLicense" 
        id="datasetLicense" 
        defaultLabel="" 
        defaultValue=""
        {* selected=$licenseId  *}
        from=$dataverseAvailableLicenses 
        translate=false 
        required=true
    }
{/fbvFormSection}