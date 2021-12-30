{**
 * templates/publishDataForm.tpl
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Extensions to Submission File Metadata Form
 *
 *}
{fbvFormSection list="true" title="Dataverse Plugin" translate=false}
	{fbvElement type="checkbox" label="plugins.generic.dataverse.submissionFileMetadata.publishData" id="publishData" checked=false}
{/fbvFormSection}

<script>
	function newWindow() {
		var newWindow = window.open("", "MsgWindow", "width=200,height=100");
		
		var title = newWindow.document.createElement("H1");       
		title.innerText = "Dataverse Terms de Use";               
		newWindow.document.body.appendChild(title); 

		var terms = newWindow.document.createElement("P");       
		terms.innerText = "terms 1";               
		newWindow.document.body.appendChild(terms); 

		//myWindow.document.write($dataverseTermsOfUse); 
	}
</script>