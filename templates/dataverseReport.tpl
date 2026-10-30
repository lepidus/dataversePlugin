{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.generic.dataverse.report.displayName"}
	</h1>

	<div class="app__contentPanel">
		<form id="dataverseReportForm" method="post" action="">
			{csrf}
			{include file="common/formErrors.tpl"}

			<h2>{translate key="plugins.generic.dataverse.report.period"}</h2>
			<div id="dataverseReportMainSection">
				<div id="filterTypeField">
					<label for="selectFilterTypeDate">{translate key="plugins.generic.dataverse.report.filterMessage"}</label>
					<select name="selectFilterTypeDate" id="selectFilterTypeDate">
						<option value="{$filterBySubmission|escape}" {if $selectFilterTypeDate == $filterBySubmission}selected{/if}>{translate key="plugins.generic.dataverse.report.filterBySubmission"}</option>
						<option value="{$filterByFinalDecision|escape}" {if $selectFilterTypeDate == $filterByFinalDecision}selected{/if}>{translate key="plugins.generic.dataverse.report.filterByDecision"}</option>
					</select>
				</div>

				<fieldset id="submittedDateFields" class="dataverseReport__dateRange" {if $selectFilterTypeDate == $filterByFinalDecision}hidden{/if}>
					<legend>{translate key="plugins.generic.dataverse.report.dateSubmittedInterval"}</legend>
					<div class="dataverseReport__dateField">
						<label for="startSubmissionDateInterval">{translate key="stats.dateRange.from"}</label>
						<input type="date" id="startSubmissionDateInterval" name="startSubmissionDateInterval" value="{$startSubmissionDateInterval|escape}"/>
					</div>
					<div class="dataverseReport__dateField">
						<label for="endSubmissionDateInterval">{translate key="plugins.generic.dataverse.report.until"}</label>
						<input type="date" id="endSubmissionDateInterval" name="endSubmissionDateInterval" value="{$endSubmissionDateInterval|escape}"/>
					</div>
				</fieldset>

				<fieldset id="finalDecisionDateFields" class="dataverseReport__dateRange" {if $selectFilterTypeDate != $filterByFinalDecision}hidden{/if}>
					<legend>{translate key="plugins.generic.dataverse.report.finalDecisionDateInterval"}</legend>
					<div class="dataverseReport__dateField">
						<label for="startFinalDecisionDateInterval">{translate key="stats.dateRange.from"}</label>
						<input type="date" id="startFinalDecisionDateInterval" name="startFinalDecisionDateInterval" value="{$startFinalDecisionDateInterval|escape}"/>
					</div>
					<div class="dataverseReport__dateField">
						<label for="endFinalDecisionDateInterval">{translate key="plugins.generic.dataverse.report.until"}</label>
						<input type="date" id="endFinalDecisionDateInterval" name="endFinalDecisionDateInterval" value="{$endFinalDecisionDateInterval|escape}"/>
					</div>
				</fieldset>
			</div>

			<p id="noticeTimeGenerateReport">
				{translate key="plugins.generic.dataverse.report.noticeTimeGenerateReport"}
			</p>

			<div id="actionsButton">
				<pkp-button :is-primary="true" type="submit">
					{translate key="plugins.generic.dataverse.report.generate"}
				</pkp-button>
				<pkp-button element="a" href="{url page="stats" op="reports"}" :is-warnable="true">
					{translate key="common.cancel"}
				</pkp-button>
			</div>
		</form>
	</div>
{/block}
