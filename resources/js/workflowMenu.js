const MENU_ITEMS = [
	{
		name: 'dataverseResearchData',
		labelKey: 'plugins.generic.dataverse.researchData',
		component: 'DataverseResearchData',
	},
	{
		name: 'dataverseDataStatement',
		labelKey: 'plugins.generic.dataverse.dataStatement.title',
		component: 'DataverseDataStatement',
	},
];

export default function registerWorkflowMenu() {
	pkp.registry.storeExtend('workflow', (piniaContext) => {
		const workflowStore = piniaContext.store;
		const {useLocalize} = pkp.modules.useLocalize;
		const {t} = useLocalize();

		workflowStore.extender.extendFn('getMenuItems', (menuItems) => [
			...menuItems,
			{
				key: 'dataverse',
				label: 'Dataverse',
				class: 'dataverse-workflow-menu',
				items: MENU_ITEMS.map(({name, labelKey}) => ({
					key: `dataverse_${name}`,
					label: t(labelKey),
					state: {
						primaryMenuItem: 'dataverse',
						secondaryMenuItem: name,
						title: `${t('semicolon', {label: 'Dataverse'})} ${t(labelKey)}`,
					},
				})),
			},
		]);

		workflowStore.extender.extendFn('getPrimaryItems', (primaryItems, args) => {
			if (args?.selectedMenuState?.primaryMenuItem !== 'dataverse') {
				return primaryItems;
			}

			const menuItem = MENU_ITEMS.find(
				({name}) => name === args.selectedMenuState.secondaryMenuItem,
			);

			if (!menuItem) {
				return primaryItems;
			}

			return [
				...primaryItems,
				{
					component: menuItem.component,
					props: {
						submission: args.submission,
						publication: args.selectedPublication,
						canEdit: args.permissions.canEditPublication,
						canPublish: args.permissions.canPublish,
					},
				},
			];
		});

		// The workflow page sets its default expanded groups during setup.
		pkp.modules.vue.nextTick(() => {
			workflowStore.setExpandedKeys([
				...Object.keys(workflowStore.sideMenuProps.expandedKeys),
				'dataverse',
			]);
		});
	});
}
