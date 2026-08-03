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
            <p>{translate key="plugins.generic.dataverse.report.filterMessage"}</p>

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
            </div>
        </div>

        <div id="actionsButton">
            <input class="pkp_button submitFormButton" type="submit" value="{translate key="plugins.generic.dataverse.report.generate"}" class="button defaultButton" />
            <input type="button" class="pkp_button submitFormButton" value="{translate key="common.cancel"}" class="button" onclick="document.location.href='{url path="index" escape=false}'" />
        </div>
    </form>
{/block}
