export interface TsbAdminConfig {
	rest: string;
	nonce: string;
	exportUrl: string;
	buildUrl: string;
}

declare global {
	const tsbAdmin: TsbAdminConfig;
	// eslint-disable-next-line no-var
	var __webpack_public_path__: string;
}
