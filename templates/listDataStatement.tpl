{if $publication->getData('dataStatementTypes') && $publication->getData('dataStatementTypes') != [$smarty.const.DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]}
	<div class="item dataStatement">
		<h2 class="label">
			{translate key="plugins.generic.dataverse.dataStatement.title"}
		</h2>
		<div class="value">
			<ul class="data_statement_list">
				{foreach from=$publication->getData('dataStatementTypes') item=type}
					{if $type === $smarty.const.DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED}
						{continue}
					{/if}
					<li>
						<p>{$allDataStatementTypes[$type]}
							{if $type === $smarty.const.DATA_STATEMENT_TYPE_REPO_AVAILABLE}
								<ul>
									{foreach from=$publication->getData('dataStatementUrls') item=url}
										<li>
											{* Only http(s) URLs become links: URLs saved before validation may use schemes such as javascript: *}
											{if $url|regex_replace:'/^https?:\/\//i':'' !== $url}
												<a href="{$url|escape}" target="_new">{$url|escape}</a>
											{else}
												{$url|escape}
											{/if}
										</li>
									{/foreach}
								</ul>
							{else if $type === $smarty.const.DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE}
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
