import {
	Card,
	CardBody,
	CardHeader,
	SelectControl,
	ToggleControl,
	TextControl,
	Button,
	Notice,
	Spinner,
	Flex,
	Modal,
	SearchControl,
} from '@wordpress/components';
import { useState, useEffect, useRef, lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { api } from './api';
import VarMenu from './VarMenu';
import type { CodeEditorHandle, MjmlError } from './MjmlCodeEditor';
import type { EmailTemplate } from './types';

// CodeMirror is heavy → load it only when the email editor renders.
const MjmlCodeEditor = lazy( () => import( './MjmlCodeEditor' ) );

// mjml-browser bundles its own lodash whose UMD wrapper assigns `window._ =
// lodash`, clobbering WordPress' global underscore. wp.media (the image picker)
// calls `_.each( list, fn, thisArg )` and relies on underscore honoring the
// third `thisArg` argument — lodash 4 ignores it, so the media frame throws
// "this.activateMode is not a function" and nothing happens. Capture underscore
// now (before the mjml chunk ever loads) so compile() can restore it.
const WP_UNDERSCORE = typeof window !== 'undefined' ? ( window as { _?: unknown } )._ : undefined;

// A block insertable into the MJML source. `image` opens the WP media picker,
// `page` opens the WP page picker; everything else inserts `mjml` verbatim.
type Snippet = { label: string; mjml?: string; image?: boolean; page?: boolean };

const SNIPPETS: Snippet[] = [
	{ label: __( 'Text', 'tsb' ), mjml: '\n        <mj-text>Your text here</mj-text>' },
	{ label: __( 'Button', 'tsb' ), mjml: '\n        <mj-button href="{{site}}" background-color="#2563eb" color="#ffffff">Click here</mj-button>' },
	{ label: __( 'Image', 'tsb' ), image: true },
	{ label: __( 'Page link', 'tsb' ), page: true },
	{ label: __( 'Divider', 'tsb' ), mjml: '\n        <mj-divider border-color="#e5e7eb" />' },
	{ label: __( 'Spacer', 'tsb' ), mjml: '\n        <mj-spacer height="20px" />' },
	{ label: __( 'Section', 'tsb' ), mjml: '\n    <mj-section background-color="#ffffff" padding="20px">\n      <mj-column><mj-text>Section content</mj-text></mj-column>\n    </mj-section>' },
	{ label: __( '2 columns', 'tsb' ), mjml: '\n    <mj-section>\n      <mj-column><mj-text>Left</mj-text></mj-column>\n      <mj-column><mj-text>Right</mj-text></mj-column>\n    </mj-section>' },
	{ label: __( 'Group', 'tsb' ), mjml: '\n    <mj-section>\n      <mj-group>\n        <mj-column><mj-text>Left</mj-text></mj-column>\n        <mj-column><mj-text>Right</mj-text></mj-column>\n      </mj-group>\n    </mj-section>' },
	{ label: __( 'Wrapper', 'tsb' ), mjml: '\n    <mj-wrapper background-color="#f8fafc" padding="20px">\n      <mj-section><mj-column><mj-text>Wrapped content</mj-text></mj-column></mj-section>\n    </mj-wrapper>' },
	{ label: __( 'Hero', 'tsb' ), mjml: '\n    <mj-hero mode="fixed-height" height="320px" background-color="#2563eb" background-position="center center" padding="100px 0px">\n      <mj-text align="center" color="#ffffff" font-size="32px" font-weight="bold">Hero title</mj-text>\n      <mj-button href="{{site}}" background-color="#ffffff" color="#2563eb">Call to action</mj-button>\n    </mj-hero>' },
	{ label: __( 'Accordion', 'tsb' ), mjml: '\n        <mj-accordion>\n          <mj-accordion-element>\n            <mj-accordion-title>Question one</mj-accordion-title>\n            <mj-accordion-text>Answer one.</mj-accordion-text>\n          </mj-accordion-element>\n          <mj-accordion-element>\n            <mj-accordion-title>Question two</mj-accordion-title>\n            <mj-accordion-text>Answer two.</mj-accordion-text>\n          </mj-accordion-element>\n        </mj-accordion>' },
	{ label: __( 'Carousel', 'tsb' ), mjml: '\n        <mj-carousel>\n          <mj-carousel-image src="https://placehold.co/600x300" />\n          <mj-carousel-image src="https://placehold.co/600x300" />\n        </mj-carousel>' },
	{ label: __( 'Social', 'tsb' ), mjml: '\n        <mj-social font-size="13px" icon-size="30px" mode="horizontal">\n          <mj-social-element name="facebook" href="https://facebook.com/">Facebook</mj-social-element>\n          <mj-social-element name="x" href="https://x.com/">X</mj-social-element>\n          <mj-social-element name="instagram" href="https://instagram.com/">Instagram</mj-social-element>\n        </mj-social>' },
	{ label: __( 'Navbar', 'tsb' ), mjml: '\n        <mj-navbar base-url="{{site}}">\n          <mj-navbar-link href="/" color="#111827">Home</mj-navbar-link>\n          <mj-navbar-link href="/about" color="#111827">About</mj-navbar-link>\n          <mj-navbar-link href="/contact" color="#111827">Contact</mj-navbar-link>\n        </mj-navbar>' },
	{ label: __( 'Table', 'tsb' ), mjml: '\n        <mj-table>\n          <tr style="border-bottom:1px solid #e5e7eb;text-align:left;">\n            <th style="padding:8px 0;">Item</th><th style="padding:8px 0;">Value</th>\n          </tr>\n          <tr><td style="padding:8px 0;">Row</td><td style="padding:8px 0;">Data</td></tr>\n        </mj-table>' },
	{ label: __( 'Raw HTML', 'tsb' ), mjml: '\n        <mj-raw><!-- custom HTML --></mj-raw>' },
];

interface WpPage {
	id: number;
	link: string;
	title: { rendered: string };
}

interface Props {
	emails: Record< string, EmailTemplate >;
	events: Record< string, string >;
	tokensByEvent: Record< string, string[] >;
	tokenLabels: Record< string, string >;
	sampleVars: Record< string, string >;
	defaults: Record< string, EmailTemplate >;
	adminEmail: string;
	/** Session type the test send + tokens belong to (default for global editors). */
	testType?: string;
	onChange: ( emails: Record< string, EmailTemplate > ) => void;
}

export default function EmailEditor( props: Props ) {
	const { emails, events, tokensByEvent, tokenLabels, sampleVars, defaults, adminEmail, testType = 'default', onChange } = props;

	const [ event, setEvent ] = useState( () => Object.keys( events )[ 0 ] || 'confirm' );
	const [ preview, setPreview ] = useState( '' );
	const [ errors, setErrors ] = useState< MjmlError[] >( [] );
	const [ compiling, setCompiling ] = useState( false );
	const [ device, setDevice ] = useState< 'desktop' | 'tablet' | 'mobile' >( 'desktop' );
	const [ withSample, setWithSample ] = useState( true );
	const [ testTo, setTestTo ] = useState( adminEmail );
	const [ testMsg, setTestMsg ] = useState< { type: 'success' | 'error'; msg: string } | null >( null );
	const [ pageOpen, setPageOpen ] = useState( false );
	const [ pages, setPages ] = useState< WpPage[] >( [] );
	const [ pagesLoading, setPagesLoading ] = useState( false );
	const [ pageSearch, setPageSearch ] = useState( '' );

	const codeRef = useRef< CodeEditorHandle >( null );
	const debounce = useRef< ReturnType< typeof setTimeout > | undefined >( undefined );
	const pageDebounce = useRef< ReturnType< typeof setTimeout > | undefined >( undefined );

	const tpl = emails[ event ];
	const tokens = tokensByEvent[ event ] || [];

	// Always merge against the LATEST emails — the async compile would otherwise
	// use a stale closure and overwrite the text the user is still typing.
	const emailsRef = useRef( emails );
	emailsRef.current = emails;
	function update( patch: Partial< EmailTemplate > ) {
		const e = emailsRef.current;
		onChange( { ...e, [ event ]: { ...e[ event ], ...patch } } );
	}

	function fill( htmlStr: string ) {
		if ( ! withSample ) {
			return htmlStr;
		}
		return htmlStr.replace( /\{\{\s*(\w+)\s*\}\}/g, ( _m, k ) => sampleVars[ k ] ?? `{{${ k }}}` );
	}

	async function compile( src: string ) {
		setCompiling( true );
		try {
			const mod = await import( 'mjml-browser' );
			// The mjml chunk just leaked lodash onto window._ — restore underscore
			// so the WP media/page pickers keep working. See WP_UNDERSCORE above.
			if ( WP_UNDERSCORE ) {
				( window as { _?: unknown } )._ = WP_UNDERSCORE;
			}
			const res = await mod.default( src, { validationLevel: 'soft' } );
			setErrors( res.errors || [] );
			if ( res.html ) {
				setPreview( res.html );
				update( { html: res.html } );
			}
		} catch ( e ) {
			setErrors( [ { message: String( e ) } ] );
		}
		setCompiling( false );
	}

	// On event switch, show the stored compiled HTML immediately (no recompile).
	useEffect( () => {
		setPreview( tpl.html );
		setErrors( [] );
	}, [ event ] );

	// Recompile whenever the template markup changes. Keyed on the rendered
	// value (not the keystroke handler) so the preview tracks the input through
	// every re-render — including the async compile's own state write.
	useEffect( () => {
		clearTimeout( debounce.current );
		debounce.current = setTimeout( () => compile( tpl.mjml ), 450 );
		return () => clearTimeout( debounce.current );
	}, [ tpl.mjml ] );

	function onMjml( v: string ) {
		update( { mjml: v } );
	}

	function insert( text: string ) {
		codeRef.current?.insertAtCursor( text );
		// CodeMirror's own onChange fires → onMjml handles compile.
	}

	function pickImage() {
		const media = ( window as { wp?: { media?: ( c: unknown ) => unknown } } ).wp?.media;
		if ( ! media ) {
			return;
		}
		const frame = media( { title: __( 'Select image', 'tsb' ), multiple: false, library: { type: 'image' } } ) as {
			on: ( ev: string, cb: () => void ) => void;
			open: () => void;
			state: () => { get: ( k: string ) => { first: () => { toJSON: () => { url: string } } } };
		};
		frame.on( 'select', () => {
			const url = frame.state().get( 'selection' ).first().toJSON().url;
			insert( `\n        <mj-image src="${ url }" alt="" />` );
		} );
		frame.open();
	}

	// Page link block — pick a published WordPress page, insert a button linking
	// to its permalink. Pages live on the wp/v2 namespace, not ours. Search runs
	// server-side so it reaches past the per_page cap.
	function loadPages( search: string ) {
		setPagesLoading( true );
		const path =
			'wp/v2/pages?per_page=100&status=publish&orderby=title&order=asc&_fields=id,link,title' +
			( search ? '&search=' + encodeURIComponent( search ) : '' );
		apiFetch< WpPage[] >( { path } )
			.then( ( res ) => setPages( res ) )
			.catch( () => setPages( [] ) )
			.finally( () => setPagesLoading( false ) );
	}

	function openPagePicker() {
		setPageOpen( true );
		// Always reopen on the full, unfiltered list.
		if ( pageSearch || ! pages.length ) {
			setPageSearch( '' );
			loadPages( '' );
		}
	}

	function onPageSearch( v: string ) {
		setPageSearch( v );
		clearTimeout( pageDebounce.current );
		pageDebounce.current = setTimeout( () => loadPages( v ), 300 );
	}

	function insertPage( p: WpPage ) {
		const title = ( p.title?.rendered || __( 'View page', 'tsb' ) ).replace( /<[^>]+>/g, '' ).trim();
		insert( `\n        <mj-button href="${ p.link }" background-color="#2563eb" color="#ffffff">${ title }</mj-button>` );
		setPageOpen( false );
	}

	function reset() {
		const d = defaults[ event ];
		if ( d ) {
			update( { subject: d.subject, mjml: d.mjml, html: d.html } );
			setPreview( d.html );
			setErrors( [] );
		}
	}

	function sendTest() {
		setTestMsg( null );
		api( 'test-email', { method: 'POST', data: { event, to: testTo, type: testType } } )
			.then( () => setTestMsg( { type: 'success', msg: __( 'Test email sent.', 'tsb' ) } ) )
			.catch( ( e: { message?: string } ) => setTestMsg( { type: 'error', msg: e?.message || __( 'Could not send.', 'tsb' ) } ) );
	}

	// Tokens used in the template that aren't valid for this event.
	const valid = new Set( tokens );
	const used = Array.from( tpl.mjml.matchAll( /\{\{\s*(\w+)\s*\}\}/g ) ).map( ( m ) => m[ 1 ] );
	const unknown = Array.from( new Set( used.filter( ( u ) => ! valid.has( u ) ) ) );

	const subjectPreview = withSample
		? tpl.subject.replace( /\{\{\s*(\w+)\s*\}\}/g, ( _m, k ) => sampleVars[ k ] ?? `{{${ k }}}` )
		: tpl.subject;

	return (
		<Card className="tsb-card">
			<CardHeader>
				{ __( 'Email templates', 'tsb' ) }
				<Button variant="tertiary" size="small" onClick={ reset } className="tsb-reset-tpl">
					{ __( 'Reset to default', 'tsb' ) }
				</Button>
			</CardHeader>
			<CardBody>
				<SelectControl
					label={ __( 'Email', 'tsb' ) }
					value={ event }
					options={ Object.keys( events ).map( ( k ) => ( { label: events[ k ], value: k } ) ) }
					onChange={ setEvent }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<div className="tsb-email-head">
					<ToggleControl
						label={ __( 'Enabled', 'tsb' ) }
						checked={ !! tpl.enabled }
						onChange={ ( v ) => update( { enabled: v ? 1 : 0 } ) }
						__nextHasNoMarginBottom
					/>
					<VarMenu tokens={ tokens } labels={ tokenLabels }>
						<TextControl
							label={ __( 'Subject', 'tsb' ) }
							value={ tpl.subject }
							onChange={ ( v ) => update( { subject: v } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</VarMenu>
					{ event === 'admin' && (
						<TextControl
							label={ __( 'Recipient', 'tsb' ) }
							value={ tpl.to || '' }
							placeholder={ adminEmail }
							onChange={ ( v ) => update( { to: v } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) }
				</div>

				<p className="tsb-help tsb-varmenu-hint">
					{ __( 'Tip: right-click the subject or MJML editor to insert a variable.', 'tsb' ) }
				</p>

				<div className="tsb-token-palette">
					<span className="tsb-help">{ __( 'Insert block:', 'tsb' ) }</span>
					{ SNIPPETS.map( ( sn ) => (
						<Button
							key={ sn.label }
							variant="secondary"
							size="small"
							onClick={ () => ( sn.image ? pickImage() : sn.page ? openPagePicker() : insert( sn.mjml || '' ) ) }
						>
							{ sn.label }
						</Button>
					) ) }
				</div>

				{ pageOpen && (
					<Modal title={ __( 'Insert page link', 'tsb' ) } onRequestClose={ () => setPageOpen( false ) } className="tsb-page-modal">
						<SearchControl
							value={ pageSearch }
							onChange={ onPageSearch }
							placeholder={ __( 'Search pages…', 'tsb' ) }
							__nextHasNoMarginBottom
						/>
						{ pagesLoading && <div className="tsb-loading-row"><Spinner /></div> }
						{ ! pagesLoading && pages.length === 0 && <p>{ pageSearch ? __( 'No pages match.', 'tsb' ) : __( 'No published pages found.', 'tsb' ) }</p> }
						<div className="tsb-page-list">
							{ pages.map( ( p ) => (
								<Button key={ p.id } variant="secondary" onClick={ () => insertPage( p ) }>
									{ ( p.title?.rendered || p.link ).replace( /<[^>]+>/g, '' ) }
								</Button>
							) ) }
						</div>
					</Modal>
				) }

				{ unknown.length > 0 && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Unknown variables for this email:', 'tsb' ) } { unknown.map( ( u ) => '{{' + u + '}}' ).join( ' ' ) }
					</Notice>
				) }

				<div className="tsb-email-grid">
					<div className="tsb-email-mjml">
						<label className="tsb-mjml-label">MJML</label>
						<VarMenu tokens={ tokens } labels={ tokenLabels } insert={ insert }>
							<Suspense fallback={ <div className="tsb-loading-row"><Spinner /></div> }>
								<MjmlCodeEditor ref={ codeRef } value={ tpl.mjml } errors={ errors } onChange={ onMjml } />
							</Suspense>
						</VarMenu>
					</div>

					<div className="tsb-email-previewwrap">
						<div className="tsb-email-previewbar">
							<span>{ __( 'Live preview', 'tsb' ) }</span>
							{ compiling && <Spinner /> }
							<span className="tsb-device-buttons">
								<ToggleControl
									label={ __( 'Sample data', 'tsb' ) }
									checked={ withSample }
									onChange={ setWithSample }
									__nextHasNoMarginBottom
								/>
								{ ( [ 'desktop', 'tablet', 'mobile' ] as const ).map( ( dv ) => (
									<Button key={ dv } size="small" variant={ device === dv ? 'primary' : 'secondary' } onClick={ () => setDevice( dv ) }>
										{ dv === 'desktop' ? __( 'Desktop', 'tsb' ) : dv === 'tablet' ? __( 'Tablet', 'tsb' ) : __( 'Mobile', 'tsb' ) }
									</Button>
								) ) }
							</span>
						</div>
						<div className="tsb-email-subjectline">
							<strong>{ __( 'Subject', 'tsb' ) }:</strong> { subjectPreview }
						</div>
						<div className={ 'tsb-email-stage is-' + device }>
							<iframe
								className="tsb-email-preview"
								title={ __( 'Email preview', 'tsb' ) }
								srcDoc={ fill( preview ) }
								style={ { width: device === 'desktop' ? '100%' : device === 'tablet' ? '600px' : '375px' } }
							/>
						</div>
					</div>
				</div>

				<Flex className="tsb-test-send" justify="flex-start" align="flex-end" gap={ 2 }>
					<TextControl
						label={ __( 'Send a test to', 'tsb' ) }
						type="email"
						value={ testTo }
						onChange={ setTestTo }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<Button variant="secondary" onClick={ sendTest } __next40pxDefaultSize>
						{ __( 'Send test email', 'tsb' ) }
					</Button>
					{ testMsg && (
						<Notice status={ testMsg.type } isDismissible onRemove={ () => setTestMsg( null ) }>
							{ testMsg.msg }
						</Notice>
					) }
				</Flex>
			</CardBody>
		</Card>
	);
}
