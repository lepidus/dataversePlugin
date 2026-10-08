import assert from 'node:assert/strict';
import test from 'node:test';
import registerWorkflowMenu from '../../resources/js/workflowMenu.js';

function getExtensions(storeOverrides = {}) {
	const extensions = {};
	globalThis.pkp = {
		registry: {storeExtend: (name, callback) => callback({store: {
			extender: {extendFn: (name, callback) => { extensions[name] = callback; }},
			sideMenuProps: {expandedKeys: {}},
			setExpandedKeys: () => {},
			...storeOverrides,
		}})},
		modules: {vue: {nextTick: (callback) => queueMicrotask(callback)}, useLocalize: {useLocalize: () => ({t: (key, params) => key === 'semicolon' ? `${params.label}:` : key})}},
	};
	registerWorkflowMenu();
	return extensions;
}

test('Dataverse groups both panels without selecting its parent or changing Publication', () => {
	const {getMenuItems} = getExtensions();
	const publication = {key: 'publication', items: [{key: 'publication_title'}]};
	const menu = getMenuItems([publication]);
	assert.equal(menu[0], publication);
	const group = menu.find(({key}) => key === 'dataverse');
	assert.ok(group);
	assert.equal(group.label, 'Dataverse');
	assert.equal(group.state, undefined);
	assert.deepEqual(group.items.map(({state}) => state.secondaryMenuItem), [
		'dataverseResearchData', 'dataverseDataStatement',
	]);
	for (const item of group.items) {
		assert.equal(item.state.primaryMenuItem, 'dataverse');
		assert.equal(item.state.title, `Dataverse: ${item.label}`);
	}
});

test('each Dataverse submenu renders its panel with the workflow permissions', () => {
	const {getPrimaryItems} = getExtensions();
	for (const component of ['DataverseResearchData', 'DataverseDataStatement']) {
		const args = {
			selectedMenuState: {primaryMenuItem: 'dataverse', secondaryMenuItem: component[0].toLowerCase() + component.slice(1)},
			submission: {id: 1}, selectedPublication: {id: 2},
			permissions: {canEditPublication: false, canPublish: true},
		};
		const items = getPrimaryItems([], args);
		assert.equal(items.length, 1);
		assert.equal(items[0].component, component);
		assert.equal(items[0].props.submission, args.submission);
		assert.equal(items[0].props.canEdit, false);
		assert.equal(items[0].props.canPublish, true);
	}
	const original = [{component: 'Publication'}];
	assert.equal(getPrimaryItems(original, {selectedMenuState: {primaryMenuItem: 'publication'}}), original);
});


test('Dataverse starts expanded after the workflow page initializes its groups', async () => {
	let expandedKeys;
	const sideMenuProps = {expandedKeys: {}};
	getExtensions({
		sideMenuProps,
		setExpandedKeys: (keys) => { expandedKeys = keys; },
	});
	// The page initializes its defaults after store creation.
	sideMenuProps.expandedKeys = {workflow: true, publication: true};
	await Promise.resolve();
	assert.deepEqual(expandedKeys, ['workflow', 'publication', 'dataverse']);
});
