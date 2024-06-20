{if $publication->getData('dataStatementTypes') && $publication->getData('dataStatementTypes') != [$dataStatementConsts['DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED']]}
	<div class="item dataStatement">
		<h2 class="label">
			{translate key="plugins.generic.dataverse.dataStatement.title"}
		</h2>
		<div class="value">
			<ul class="data_statement_list">
				{foreach from=$publication->getData('dataStatementTypes') item=type}
					{if $type === $dataStatementConsts['DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED']}
						{continue}
					{/if}
					<li>
						<p>
							{$dataStatementMessages[$type]}
							{if $type === $dataStatementConsts['DATA_STATEMENT_TYPE_REPO_AVAILABLE']}
								<ul>
									{foreach from=$publication->getData('dataStatementUrls') item=url}
										<li>
											<a href="{$url|escape}" target="_new">{$url|escape}</a>
										</li>
									{/foreach}
								</ul>
							{else if $type === $dataStatementConsts['DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE']}
								<ul>
									<li>{$publication->getLocalizedData('dataStatementReason')|escape}</li>
								</ul>
							{/if}
						</p>
					</li>
				{/foreach}
			</ul>
		</div>
	</div>
{/if}
