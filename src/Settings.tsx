import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Spinner,
	Notice,
	TabPanel,
	ToggleControl,
	FormTokenField,
	__experimentalNumberControl as NumberControl,
	__experimentalVStack as VStack,
	__experimentalGrid as Grid,
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

	const dirty = saved !== '' && JSON.stringify( d ) !== saved;

	/* ---- Form & notifications ---- */
	const tabForm = (
		<VStack spacing={ 5 }>
			<Card className="tsb-card">
				<CardHeader>{ __( 'Form fields', 'tsb' ) }</CardHeader>
				<CardBody>
					<p className="tsb-help">{ __( 'Name and email are always shown and required. Add your own fields below — drag to reorder. Fields are shared across all session types.', 'tsb' ) }</p>
					<FieldBuilder fields={ d.fields } types={ m.fieldTypes } onChange={ ( fields ) => set( 'fields', fields ) } />
					<p className="tsb-help tsb-spam-note">{ __( 'Spam is blocked automatically with a built-in honeypot — nothing to configure.', 'tsb' ) }</p>
				</CardBody>
			</Card>
			<Card className="tsb-card">
				<CardHeader>{ __( 'Admin notification', 'tsb' ) }</CardHeader>
				<CardBody>
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
				</CardBody>
			</Card>
		</VStack>
	);

	/* ---- Availability (global rules + holidays + time off) ---- */
	const countries = m.countries;
	const tabAvailability = (
		<VStack spacing={ 5 }>
			<Grid columns={ 2 } gap={ 5 } className="tsb-cards-2">
			<Card className="tsb-card">
				<CardHeader>{ __( 'Booking window', 'tsb' ) }</CardHeader>
				<CardBody>
					<p className="tsb-help">{ __( 'Site-wide limits applied to every session type.', 'tsb' ) }</p>
					<VStack spacing={ 4 }>
						<NumberControl
							label={ __( 'Days ahead', 'tsb' ) }
							min={ 1 }
							value={ String( d.days_ahead ) }
							help={ __( 'How far into the future visitors can book.', 'tsb' ) }
							onChange={ ( v?: string ) => set( 'days_ahead', v === '' || v == null ? 1 : parseInt( v, 10 ) ) }
							__next40pxDefaultSize
						/>
						<NumberControl
							label={ __( 'Minimum lead time (hours)', 'tsb' ) }
							min={ 0 }
							value={ String( d.lead_hours ) }
							help={ __( '0 = bookable right now.', 'tsb' ) }
							onChange={ ( v?: string ) => set( 'lead_hours', v === '' || v == null ? 0 : parseInt( v, 10 ) ) }
							__next40pxDefaultSize
						/>
					</VStack>
				</CardBody>
			</Card>

			<Card className="tsb-card">
				<CardHeader>{ __( 'Public holidays', 'tsb' ) }</CardHeader>
				<CardBody>
					<VStack spacing={ 4 }>
						<ToggleControl
							label={ __( 'Block public holidays', 'tsb' ) }
							checked={ !! d.block_holidays }
							onChange={ ( v ) => set( 'block_holidays', ( v ? 1 : 0 ) as never ) }
							__nextHasNoMarginBottom
						/>
						<FormTokenField
							label={ __( 'Countries', 'tsb' ) }
							value={ d.holiday_countries.map( ( c ) => countries[ c ] || c ) }
							suggestions={ Object.values( countries ) }
							onChange={ ( tokens ) => {
								const nameToCode: Record< string, string > = {};
								Object.keys( countries ).forEach( ( code ) => { nameToCode[ countries[ code ] ] = code; } );
								const codes = tokens
									.map( ( tk ) => ( typeof tk === 'string' ? tk : tk.value ) )
									.map( ( name ) => nameToCode[ name ] || name )
									.filter( Boolean ) as string[];
								set( 'holiday_countries', ( codes.length ? codes : [ 'DK' ] ) as never );
							} }
							__experimentalExpandOnFocus
							__nextHasNoMarginBottom
						/>
						<p className="tsb-help">{ __( 'Holidays are fetched from date.nager.at (cached).', 'tsb' ) }</p>
					</VStack>
				</CardBody>
			</Card>
			</Grid>

			<Blocks />
		</VStack>
	);

	/* ---- Integrations (Google) ---- */
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
	const GLOBAL_TABS = [ 'form', 'availability', 'google' ];
	const tabs = [
		{ name: 'types', title: __( 'Session types', 'tsb' ) },
		{ name: 'form', title: __( 'Form & notifications', 'tsb' ) },
		{ name: 'availability', title: __( 'Availability', 'tsb' ) },
		{ name: 'google', title: __( 'Integrations', 'tsb' ) },
	];

	// Remember the open tab in the URL hash (#…) so a reload keeps it.
	const urlTab = window.location.hash.slice( 1 ); // drop the leading '#'
	const initialTab = tabs.some( ( t ) => t.name === urlTab ) ? urlTab : undefined;
	function onSelectTab( name: string ) {
		window.history.replaceState( null, '', '#' + name );
	}

	return (
		<>
			<h1>{ __( 'Booking settings', 'tsb' ) }</h1>
			{ notice && (
				<Notice status={ notice.type } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }
			<TabPanel className="tsb-tabs" tabs={ tabs } initialTabName={ initialTab } onSelect={ onSelectTab }>
				{ ( tab ) => {
					if ( tab.name === 'types' ) {
						return <TypeManager />;
					}
					const body =
						tab.name === 'form' ? tabForm :
						tab.name === 'availability' ? tabAvailability :
						tabGoogle;
					return (
						<div className="tsb-tab-body">
							{ body }
							{ GLOBAL_TABS.includes( tab.name ) && (
								<div className="tsb-savebar">
									{ dirty && (
										<span className="tsb-unsaved">{ __( 'Unsaved changes', 'tsb' ) }</span>
									) }
									<Button variant="primary" isBusy={ saving } disabled={ ! dirty } onClick={ save } __next40pxDefaultSize>
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
