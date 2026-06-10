import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Spinner,
	Notice,
	TabPanel,
	ToggleControl,
	SelectControl,
	TextControl,
	__experimentalNumberControl as NumberControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';
import Blocks from './Blocks';
import FieldBuilder from './FieldBuilder';
import EmailEditor from './EmailEditor';
import TypeManager from './TypeManager';
import GoogleSettings from './GoogleSettings';
import type { Settings as TSettings, Meta } from './types';

export default function Settings() {
	const [ data, setData ] = useState< TSettings | null >( null );
	const [ meta, setMeta ] = useState< Meta | null >( null );
	const [ saved, setSaved ] = useState( '' ); // JSON of last-saved settings
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState< { type: 'success' | 'error'; msg: string } | null >( null );

	useEffect( () => {
		api< { settings: TSettings; meta: Meta } >( 'settings' ).then( ( r ) => {
			setData( r.settings );
			setMeta( r.meta );
			setSaved( JSON.stringify( r.settings ) );
		} );
	}, [] );

	if ( ! data || ! meta ) {
		return <div className="tsb-loading-row"><Spinner /></div>;
	}
	const d = data;
	const m = meta;

	function set< K extends keyof TSettings >( k: K, v: TSettings[ K ] ) {
		setData( ( prev ) => ( prev ? { ...prev, [ k ]: v } : prev ) );
	}
	function save() {
		setSaving( true );
		api< { settings: TSettings } >( 'settings', { method: 'POST', data: d } )
			.then( ( r ) => {
				setData( r.settings );
				setSaved( JSON.stringify( r.settings ) );
				setSaving( false );
				setNotice( { type: 'success', msg: __( 'Settings saved.', 'tsb' ) } );
			} )
			.catch( () => {
				setSaving( false );
				setNotice( { type: 'error', msg: __( 'Error', 'tsb' ) } );
			} );
	}

	const txt = ( k: keyof TSettings, label: string, help?: string ) => (
		<TextControl label={ label } value={ String( d[ k ] || '' ) } help={ help } onChange={ ( v ) => set( k, v as never ) } __nextHasNoMarginBottom __next40pxDefaultSize />
	);
	const tog = ( k: keyof TSettings, label: string ) => (
		<ToggleControl label={ label } checked={ !! d[ k ] } onChange={ ( v ) => set( k, ( v ? 1 : 0 ) as never ) } __nextHasNoMarginBottom />
	);

	/* ---- Form ---- */
	const tabForm = (
		<VStack spacing={ 5 }>
			<Card className="tsb-card">
				<CardHeader>{ __( 'Form fields', 'tsb' ) }</CardHeader>
				<CardBody>
					<p className="tsb-help">{ __( 'Name and email are always shown and required. Add your own fields below — drag to reorder. Fields are shared across all session types.', 'tsb' ) }</p>
					<FieldBuilder fields={ d.fields } types={ m.fieldTypes } onChange={ ( fields ) => set( 'fields', fields ) } />
				</CardBody>
			</Card>
			<Card className="tsb-card tsb-card-narrow">
				<CardHeader>{ __( 'Consent (GDPR)', 'tsb' ) }</CardHeader>
				<CardBody>
					<VStack spacing={ 3 }>
						{ tog( 'consent_enable', __( 'Require consent checkbox', 'tsb' ) ) }
						{ txt( 'consent_text', __( 'Consent text', 'tsb' ) ) }
						{ txt( 'consent_link_text', __( 'Link text', 'tsb' ) ) }
						{ txt( 'consent_url', __( 'Link URL', 'tsb' ) ) }
					</VStack>
				</CardBody>
			</Card>
		</VStack>
	);

	/* ---- Notifications (global admin email) ---- */
	const tabNotifications = (
		<>
			<p className="tsb-help">{ __( 'Sent to you when a booking is made — shared across all session types. Customer-facing emails (confirmation, reminder, etc.) are configured per session type.', 'tsb' ) }</p>
			<EmailEditor
				emails={ d.emails }
				events={ { admin: m.emailEvents.admin } }
				tokensByEvent={ m.tokensByEvent }
				tokenLabels={ m.tokenLabels }
				sampleVars={ m.sampleVars }
				defaults={ m.emailDefaults }
				adminEmail={ m.adminEmail }
				onChange={ ( emails ) => set( 'emails', emails ) }
			/>
		</>
	);

	/* ---- Spam ---- */
	const tabSpam = (
		<Card className="tsb-card tsb-card-narrow">
			<CardHeader>{ __( 'Spam protection', 'tsb' ) }</CardHeader>
			<CardBody>
				<VStack spacing={ 4 }>
					<SelectControl
						label={ __( 'Method', 'tsb' ) }
						value={ d.captcha_mode }
						options={ Object.keys( m.captchaModes ).map( ( k ) => ( { label: m.captchaModes[ k ], value: k } ) ) }
						onChange={ ( v ) => set( 'captcha_mode', v ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					{ txt( 'captcha_site', __( 'Site key', 'tsb' ) ) }
					{ txt( 'captcha_secret', __( 'Secret key', 'tsb' ) ) }
					<NumberControl
						label={ __( 'v3 minimum score', 'tsb' ) }
						min={ 0 }
						max={ 1 }
						step={ 0.1 }
						value={ String( d.captcha_min_score ) }
						onChange={ ( v?: string ) => set( 'captcha_min_score', parseFloat( v || '0' ) || 0 ) }
						__next40pxDefaultSize
					/>
				</VStack>
			</CardBody>
		</Card>
	);

	const dirty = saved !== '' && JSON.stringify( d ) !== saved;

	/* ---- Google ---- */
	const tabGoogle = (
		<GoogleSettings
			clientId={ d.google_client_id }
			clientSecret={ d.google_client_secret }
			calendarId={ d.google_calendar_id }
			dirty={ dirty }
			onChange={ ( k, v ) => set( k, v as never ) }
		/>
	);

	// Tabs that edit the global `d` object and share the bottom save bar.
	const GLOBAL_TABS = [ 'form', 'notifications', 'spam', 'google' ];
	const tabs = [
		{ name: 'types', title: __( 'Session types', 'tsb' ) },
		{ name: 'form', title: __( 'Form', 'tsb' ) },
		{ name: 'notifications', title: __( 'Notifications', 'tsb' ) },
		{ name: 'google', title: __( 'Google', 'tsb' ) },
		{ name: 'spam', title: __( 'Spam protection', 'tsb' ) },
		{ name: 'blocks', title: __( 'Blocks', 'tsb' ) },
	];

	return (
		<>
			<h1>{ __( 'Booking settings', 'tsb' ) }</h1>
			{ notice && (
				<Notice status={ notice.type } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }
			<TabPanel className="tsb-tabs" tabs={ tabs }>
				{ ( tab ) => {
					if ( tab.name === 'types' ) {
						return <TypeManager />;
					}
					if ( tab.name === 'blocks' ) {
						return <Blocks />;
					}
					const body =
						tab.name === 'form' ? tabForm :
						tab.name === 'notifications' ? tabNotifications :
						tab.name === 'google' ? tabGoogle : tabSpam;
					return (
						<div className="tsb-tab-body">
							{ body }
							{ GLOBAL_TABS.includes( tab.name ) && (
								<div className="tsb-savebar">
									{ saved !== '' && JSON.stringify( d ) !== saved && (
										<span className="tsb-unsaved">{ __( 'Unsaved changes', 'tsb' ) }</span>
									) }
									<Button variant="primary" isBusy={ saving } disabled={ saved !== '' && JSON.stringify( d ) === saved } onClick={ save } __next40pxDefaultSize>
										{ __( 'Save settings', 'tsb' ) }
									</Button>
								</div>
							) }
						</div>
					);
				} }
			</TabPanel>
		</>
	);
}
