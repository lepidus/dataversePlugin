{extends file="layouts/backend.tpl"}

{block name="page"}
    <h1 class="app__pageHeading">
		{translate key="plugins.generic.dataverse.report.displayName"}
	</h1>

    <div class="app__contentPanel">
    <form id="dataverseReportForm" method="post" action="">
        {include file="common/formErrors.tpl"}

        <h2>{translate key="plugins.generic.dataverse.report.period"}</h2>
        <div id="dataverseReportMainSection">
            <div id="filterTypeField">
                <p>{translate key="plugins.generic.dataverse.report.filterMessage"}</p>
                <select name="selectFilterTypeDate" id="selectFilterTypeDate">
                    <option value="filterBySubmission">{translate key="plugins.generic.dataverse.report.filterBySubmission"}</option>
                    <option value="filterByFinalDecision">{translate key="plugins.generic.dataverse.report.filterByDecision"}</option>
                </select>
            </div>

            <div id="dateFilterFields">
                <!-- Submitted Date -->
                <fieldset id="submittedDateFields" class="search_advanced">
                    <legend>
                        {translate key="plugins.generic.dataverse.report.dateSubmittedInterval"}
                    </legend>
                    <div class="date_range">
                        <div class="from">
                            <label class="label">
                                {translate key="stats.dateRange.from"}
                            </label>
                            <input type="date" id='startSubmissionDateInterval' name='startSubmissionDateInterval' from=$startSubmissionDateInterval defaultValue=$startSubmissionDateInterval value="{$years[0]|escape}"/>
                        </div>
                        <div class="to">
                            <label class="label">
                                {translate key="plugins.generic.dataverse.report.until"}
                            </label>
                            <input type="date" id='endSubmissionDateInterval' name='endSubmissionDateInterval' from=$endSubmissionDateInterval defaultValue=$endSubmissionDateInterval value="{$years[1]|escape}"/>
                        </div>
                    </div>
                </fieldset>

                <!-- Final Decision Date-->
                <fieldset id="finalDecisionDateFields" class="search_advanced" hidden="true">
                    <legend>
                        {translate key="plugins.generic.dataverse.report.finalDecisionDateInterval"}
                    </legend>
                    <div class="date_range">
                        <div class="from">
                            <label class="label">
                                {translate key="stats.dateRange.from"}
                            </label>
                            <input type="date" id='startFinalDecisionDateInterval' name='startFinalDecisionDateInterval' from=$startFinalDecisionDateInterval defaultValue=$startFinalDecisionDateInterval value="{$years[0]|escape}"/>
                        </div>
                        <div class="to">
                            <label class="label">
                                {translate key="plugins.generic.dataverse.report.until"}
                            </label>
                            <input type="date" id='endFinalDecisionDateInterval' name='endFinalDecisionDateInterval' from=$endFinalDecisionDateInterval defaultValue=$endFinalDecisionDateInterval value="{$years[1]|escape}"/>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <p id="noticeTimeGenerateReport">
            {translate key="plugins.generic.dataverse.report.noticeTimeGenerateReport"}
        </p>

        <div id="actionsButton">
            <input class="pkp_button submitFormButton" type="submit" value="{translate key="plugins.generic.dataverse.report.generate"}" class="button defaultButton" />
            <input type="button" class="pkp_button submitFormButton" value="{translate key="common.cancel"}" class="button" onclick="document.location.href='{url path="index" escape=false}'" />
        </div>
    </form>

    <script>
        $(function() {ldelim}
            let filterTypeSelection = document.getElementById('selectFilterTypeDate');
            let submissionDiv = document.getElementById('submittedDateFields');
            let decisionDiv = document.getElementById('finalDecisionDateFields');

            filterTypeSelection.addEventListener("change", function(){ldelim}
                let selectedValue = filterTypeSelection.value;

                submissionDiv.hidden = (selectedValue == 'filterByFinalDecision');
                decisionDiv.hidden = (selectedValue == 'filterBySubmission');
            {rdelim});
        {rdelim});
    </script>
{/block}
