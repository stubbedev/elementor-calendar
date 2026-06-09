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
} from '@wordpress/components';
import { useState, useEffect, useRef, lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';
import type { CodeEditorHandle, MjmlError } from './MjmlCodeEditor';
import type { EmailTemplate } from './types';

// CodeMirror is heavy → load it only when the email editor renders.
const MjmlCodeEditor = lazy( () => import( './MjmlCodeEditor' ) );

const SNIPPETS: Array< { label: string; mjml?: string; image?: boolean } > = [
	{ label: __( 'Button', 'tsb' ), mjml: '\n        <mj-button href="{{site}}" background-color="#2563eb" color="#ffffff">Click here</mj-button>' },
	{ label: __( 'Divider', 'tsb' ), mjml: '\n        <mj-divider border-color="#e5e7eb" />' },
	{ label: __( 'Spacer', 'tsb' ), mjml: '\n        <mj-spacer height="20px" />' },
	{ label: __( '2 columns', 'tsb' ), mjml: '\n    <mj-section>\n      <mj-column><mj-text>Left</mj-text></mj-column>\n      <mj-column><mj-text>Right</mj-text></mj-column>\n    </mj-section>' },
	{ label: __( 'Image', 'tsb' ), image: true },
];

interface Props {
	emails: Record< string, EmailTemplate >;
	events: Record< string, string >;
	tokensByEvent: Record< string, string[] >;
	tokenLabels: Record< string, string >;
	sampleVars: Record< string, string >;
	defaults: Record< string, EmailTemplate >;
	adminEmail: string;
	onChange: ( emails: Record< string, EmailTemplate > ) => void;
}

export default function EmailEditor( props: Props ) {
	const { emails, events, tokensByEvent, tokenLabels, sampleVars, defaults, adminEmail, onChange } = props;

	const [ event, setEvent ] = useState( 'confirm' );
	const [ preview, setPreview ] = useState( '' );
	const [ errors, setErrors ] = useState< MjmlError[] >( [] );
	const [ compiling, setCompiling ] = useState( false );
	const [ device, setDevice ] = useState< 'desktop' | 'tablet' | 'mobile' >( 'desktop' );
	const [ withSample, setWithSample ] = useState( true );
	const [ testTo, setTestTo ] = useState( adminEmail );
	const [ testMsg, setTestMsg ] = useState< { type: 'success' | 'error'; msg: string } | null >( null );

	const codeRef = useRef< CodeEditorHandle >( null );
	const debounce = useRef< ReturnType< typeof setTimeout > | undefined >( undefined );

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
		api( 'test-email', { method: 'POST', data: { event, to: testTo } } )
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
					<TextControl
						label={ __( 'Subject', 'tsb' ) }
						value={ tpl.subject }
						onChange={ ( v ) => update( { subject: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
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

				<div className="tsb-token-palette">
					<span className="tsb-help">{ __( 'Insert variable:', 'tsb' ) }</span>
					{ tokens.map( ( tk ) => (
						<Button
							key={ tk }
							variant="secondary"
							size="small"
							title={ tokenLabels[ tk ] || '' }
							onClick={ () => insert( '{{' + tk + '}}' ) }
						>
							{ '{{' + tk + '}}' }
						</Button>
					) ) }
				</div>

				<div className="tsb-token-palette">
					<span className="tsb-help">{ __( 'Insert block:', 'tsb' ) }</span>
					{ SNIPPETS.map( ( sn ) => (
						<Button
							key={ sn.label }
							variant="secondary"
							size="small"
							onClick={ () => ( sn.image ? pickImage() : insert( sn.mjml || '' ) ) }
						>
							{ sn.label }
						</Button>
					) ) }
				</div>

				{ unknown.length > 0 && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Unknown variables for this email:', 'tsb' ) } { unknown.map( ( u ) => '{{' + u + '}}' ).join( ' ' ) }
					</Notice>
				) }

				<div className="tsb-email-grid">
					<div className="tsb-email-mjml">
						<label className="tsb-mjml-label">MJML</label>
						<Suspense fallback={ <div className="tsb-loading-row"><Spinner /></div> }>
							<MjmlCodeEditor ref={ codeRef } value={ tpl.mjml } errors={ errors } onChange={ onMjml } />
						</Suspense>
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
