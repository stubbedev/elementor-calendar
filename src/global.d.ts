export interface TsbAdminConfig {
	rest: string;
	nonce: string;
	exportUrl: string;
}

declare global {
	const tsbAdmin: TsbAdminConfig;
}
