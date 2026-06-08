declare module 'mjml-browser' {
	interface MjmlResult {
		html: string;
		errors: Array< { message?: string } >;
	}
	const mjml2html: ( mjml: string, opts?: Record< string, unknown > ) => Promise< MjmlResult >;
	export default mjml2html;
}
