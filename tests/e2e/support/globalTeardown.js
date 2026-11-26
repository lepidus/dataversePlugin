import {rmSync} from 'node:fs';

export default async function globalTeardown() {
	if (process.env.DATAVERSE_E2E_AUTH_DIR) {
		rmSync(process.env.DATAVERSE_E2E_AUTH_DIR, {recursive: true, force: true});
	}
}
