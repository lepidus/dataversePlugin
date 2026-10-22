import assert from 'node:assert/strict';
import test from 'node:test';
import {readFileSync} from 'node:fs';
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
		modules: {vue: {shallowRef: (value) => ({value}), nextTick: (callback) => queueMicrotask(callback)}, useLocalize: {useLocalize: () => ({t: (key, params) => key === 'semicolon' ? `${params.label}:` : key})}},
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


test('dataset citation is rendered in the workflow header only for its research data panel', () => {
	const {getPrimaryItems, getPrimaryControlsLeft} = getExtensions();
	const args = {
		selectedMenuState: {primaryMenuItem: 'dataverse', secondaryMenuItem: 'dataverseResearchData'},
		submission: {id: 1}, selectedPublication: {id: 2},
		permissions: {canEditPublication: true, canPublish: false},
	};
	assert.equal(typeof getPrimaryControlsLeft, 'function');
	assert.deepEqual(getPrimaryControlsLeft([], args), []);
	const panel = getPrimaryItems([], args)[0];
	const state = {hasDataset: true, citation: 'Dataset citation', datasetIsPublished: false};
	panel.props.onDatasetStateChange(state);
	const header = getPrimaryControlsLeft([], args);
	assert.equal(header.length, 1);
	assert.equal(header[0].component, 'DataverseDatasetHeader');
	assert.equal(header[0].props.state, state);
	assert.deepEqual(getPrimaryControlsLeft([], {...args, submission: {id: 3}}), []);
	assert.deepEqual(getPrimaryControlsLeft([], {...args,
		selectedMenuState: {primaryMenuItem: 'dataverse', secondaryMenuItem: 'dataverseDataStatement'},
	}), []);
	for (const unavailable of [null, {hasDataset: false}, {hasDataset: true, error: 'Unavailable'}]) {
		panel.props.onDatasetStateChange(unavailable);
		assert.deepEqual(getPrimaryControlsLeft([], args), []);
	}
});


test('workflow menu labels are included in the generated browser translation registry', () => {
	const registeredKeys = JSON.parse(readFileSync(
		new URL('../../registry/uiLocaleKeysBackend.json', import.meta.url), 'utf8',
	));
	const {getMenuItems} = getExtensions();
	const group = getMenuItems([]).find(({key}) => key === 'dataverse');
	for (const item of group.items) {
		assert.ok(registeredKeys.includes(item.label), `Missing browser translation: ${item.label}`);
	}
});
