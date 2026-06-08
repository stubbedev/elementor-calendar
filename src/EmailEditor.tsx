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
} from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { EmailTemplate } from './types';

const SAMPLE: Record< string, string > = {
	name: 'Jane Doe',
	email: 'jane@example.com',
	phone: '12 34 56 78',
	message: 'Looking forward to it!',
	date: '2026-06-20',
	time: '09:00',
	ref: '42',
	old_date: '2026-06-18',
	old_time: '14:00',
	site: 'Demo Site',
};

function fill( html: string ) {
	return html.replace( /\{\{\s*(\w+)\s*\}\}/g, ( _m, k ) => SAMPLE[ k ] ?? '' );
}

interface Props {
	emails: Record< string, EmailTemplate >;
	events: Record< string, string >;
	tokens: string[];
	adminEmail: string;
	onChange: ( emails: Record< string, EmailTemplate > ) => void;
}

export default function EmailEditor( { emails, events, tokens, adminEmail, onChange }: Props ) {
	const [ event, setEvent ] = useState( 'confirm' );
	const [ preview, setPreview ] = useState( '' );
	const [ errors, setErrors ] = useState< string[] >( [] );
	const [ compiling, setCompiling ] = useState( false );
	const [ device, setDevice ] = useState< 'desktop' | 'tablet' | 'mobile' >( 'desktop' );
	const taRef = useRef< HTMLTextAreaElement | null >( null );
	const debounce = useRef< ReturnType< typeof setTimeout > | undefined >( undefined );

	const tpl = emails[ event ];

	function update( patch: Partial< EmailTemplate > ) {
		onChange( { ...emails, [ event ]: { ...emails[ event ], ...patch } } );
	}

	async function compile( src: string ) {
		setCompiling( true );
		try {
			const mod = await import( 'mjml-browser' );
			const res = await mod.default( src, { validationLevel: 'soft' } );
			setErrors( ( res.errors || [] ).map( ( e ) => e.message || String( e ) ) );
			if ( res.html ) {
				setPreview( res.html );
				update( { html: res.html } );
			}
		} catch ( e ) {
			setErrors( [ String( e ) ] );
		}
		setCompiling( false );
	}

	// On event switch, show the stored compiled HTML immediately (no recompile).
	useEffect( () => {
		setPreview( tpl.html );
		setErrors( [] );
	}, [ event ] );

	function onMjml( v: string ) {
		update( { mjml: v } );
		clearTimeout( debounce.current );
		debounce.current = setTimeout( () => compile( v ), 450 );
	}

	function insertToken( token: string ) {
		const ta = taRef.current;
		const ins = '{{' + token + '}}';
		const cur = tpl.mjml;
		const start = ta ? ta.selectionStart : cur.length;
		const end = ta ? ta.selectionEnd : cur.length;
		const next = cur.slice( 0, start ) + ins + cur.slice( end );
		onMjml( next );
		requestAnimationFrame( () => {
			if ( ta ) {
				ta.focus();
				ta.selectionStart = ta.selectionEnd = start + ins.length;
			}
		} );
	}

	return (
		<Card className="tsb-card">
			<CardHeader>{ __( 'Email templates', 'tsb' ) }</CardHeader>
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
						<Button key={ tk } variant="secondary" size="small" onClick={ () => insertToken( tk ) }>
							{ '{{' + tk + '}}' }
						</Button>
					) ) }
				</div>

				<div className="tsb-email-grid">
					<div className="tsb-email-mjml">
						<label className="tsb-mjml-label">MJML</label>
						<textarea
							ref={ taRef }
							className="tsb-mjml-textarea"
							value={ tpl.mjml }
							rows={ 18 }
							spellCheck={ false }
							onChange={ ( e ) => onMjml( e.target.value ) }
						/>
						{ errors.length > 0 && (
							<Notice status="warning" isDismissible={ false }>
								{ errors.slice( 0, 3 ).join( ' · ' ) }
							</Notice>
						) }
					</div>
					<div className="tsb-email-previewwrap">
						<div className="tsb-email-previewbar">
							<span>{ __( 'Live preview', 'tsb' ) }</span>
							{ compiling && <Spinner /> }
							<span className="tsb-device-buttons">
								{ ( [ 'desktop', 'tablet', 'mobile' ] as const ).map( ( dv ) => (
									<Button
										key={ dv }
										size="small"
										variant={ device === dv ? 'primary' : 'secondary' }
										onClick={ () => setDevice( dv ) }
									>
										{ dv === 'desktop' ? __( 'Desktop', 'tsb' ) : dv === 'tablet' ? __( 'Tablet', 'tsb' ) : __( 'Mobile', 'tsb' ) }
									</Button>
								) ) }
							</span>
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
			</CardBody>
		</Card>
	);
}
