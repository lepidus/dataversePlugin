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

		workflowStore.extender.extendFn('getMenuItems', (menuItems) =>
			menuItems.map((menuItem) => {
				if (menuItem.key !== 'publication' || !menuItem.items) {
					return menuItem;
				}

				return {
					...menuItem,
					items: [
						...menuItem.items,
						...MENU_ITEMS.map(({name, labelKey}) => ({
							key: `publication_${name}`,
							label: t(labelKey),
							state: {
								primaryMenuItem: 'publication',
								secondaryMenuItem: name,
								title: `${t('semicolon', {
									label: t('submission.publication'),
								})} ${t(labelKey)}`,
							},
						})),
					],
				};
			}),
		);

		workflowStore.extender.extendFn('getPrimaryItems', (primaryItems, args) => {
			if (args?.selectedMenuState?.primaryMenuItem !== 'publication') {
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
	});
}
