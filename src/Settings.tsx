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
	TextareaControl,
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
import VarMenu from './VarMenu';
import type { Settings as TSettings, Meta, WeekDay } from './types';

function hourOptions( from: number, to: number ) {
	const out = [];
	for ( let h = from; h <= to; h++ ) {
		out.push( { label: ( h < 10 ? '0' + h : String( h ) ) + ':00', value: String( h ) } );
	}
	return out;
}

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
	function setWeek( day: string, k: keyof WeekDay, v: number ) {
		const week = { ...d.week, [ day ]: { ...d.week[ day ], [ k ]: v } };
		set( 'week', week );
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

	// control helpers
	const num = ( k: keyof TSettings, label: string, min = 0, help?: string ) => (
		<NumberControl
			label={ label }
			min={ min }
			value={ String( d[ k ] ) }
			help={ help }
			onChange={ ( v?: string ) => set( k, ( v === '' || v == null ? 0 : parseInt( v, 10 ) ) as never ) }
			__next40pxDefaultSize
		/>
	);
	const txt = ( k: keyof TSettings, label: string, help?: string ) => (
		<TextControl label={ label } value={ String( d[ k ] || '' ) } help={ help } onChange={ ( v ) => set( k, v as never ) } __nextHasNoMarginBottom __next40pxDefaultSize />
	);
	const area = ( k: keyof TSettings, label: string ) => (
		<TextareaControl label={ label } value={ String( d[ k ] || '' ) } rows={ 5 } onChange={ ( v ) => set( k, v as never ) } __nextHasNoMarginBottom />
	);
	const tog = ( k: keyof TSettings, label: string ) => (
		<ToggleControl label={ label } checked={ !! d[ k ] } onChange={ ( v ) => set( k, ( v ? 1 : 0 ) as never ) } __nextHasNoMarginBottom />
	);
	const hour = ( value: number, onChange: ( n: number ) => void, from: number, to: number, label?: string, disabled?: boolean ) => (
		<SelectControl label={ label } value={ String( value ) } options={ hourOptions( from, to ) } disabled={ disabled } onChange={ ( v ) => onChange( parseInt( v, 10 ) ) } __nextHasNoMarginBottom __next40pxDefaultSize />
	);

	/* ---- Availability ---- */
	const tabAvail = (
		<VStack spacing={ 5 }>
			<Card className="tsb-card">
				<CardHeader>{ __( 'Base business hours', 'tsb' ) }</CardHeader>
				<CardBody>
					<Grid columns={ 2 } gap={ 4 } className="tsb-hours-grid">
						{ hour( d.base_start, ( n ) => set( 'base_start', n ), 0, 23, __( 'From', 'tsb' ) ) }
						{ hour( d.base_end, ( n ) => set( 'base_end', n ), 1, 24, __( 'To', 'tsb' ) ) }
					</Grid>
					<p className="tsb-help">{ __( 'Days set to “Follow base hours” use these.', 'tsb' ) }</p>
				</CardBody>
			</Card>

			<Card className="tsb-card">
				<CardHeader>{ __( 'Opening hours per weekday', 'tsb' ) }</CardHeader>
				<CardBody>
					<div className="tsb-week">
						<div className="tsb-week-row tsb-week-head">
							<span>{ __( 'Day', 'tsb' ) }</span>
							<span>{ __( 'Open', 'tsb' ) }</span>
							<span>{ __( 'Follow base', 'tsb' ) }</span>
							<span>{ __( 'From', 'tsb' ) }</span>
							<span>{ __( 'To', 'tsb' ) }</span>
						</div>
						{ [ '1', '2', '3', '4', '5', '6', '7' ].map( ( day ) => {
							const w = d.week[ day ] || { open: 0, use_base: 1, start: 9, end: 17 };
							const base = !! w.use_base;
							return (
								<div key={ day } className={ 'tsb-week-row' + ( w.open ? '' : ' is-closed' ) }>
									<span className="tsb-week-day">{ m.weekdays[ day ] }</span>
									<ToggleControl
										label={ m.weekdays[ day ] + ' — ' + __( 'Open', 'tsb' ) }
										checked={ !! w.open }
										onChange={ ( v ) => setWeek( day, 'open', v ? 1 : 0 ) }
										className="tsb-toggle-bare"
										__nextHasNoMarginBottom
									/>
									<ToggleControl
										label={ m.weekdays[ day ] + ' — ' + __( 'Follow base', 'tsb' ) }
										checked={ base }
										onChange={ ( v ) => setWeek( day, 'use_base', v ? 1 : 0 ) }
										className="tsb-toggle-bare"
										__nextHasNoMarginBottom
									/>
									{ hour( base ? d.base_start : w.start, ( n ) => setWeek( day, 'start', n ), 0, 23, undefined, base || ! w.open ) }
									{ hour( base ? d.base_end : w.end, ( n ) => setWeek( day, 'end', n ), 1, 24, undefined, base || ! w.open ) }
								</div>
							);
						} ) }
					</div>
				</CardBody>
			</Card>

			<Grid columns={ 2 } gap={ 5 } className="tsb-cards-2">
				<Card className="tsb-card">
					<CardHeader>{ __( 'Slots', 'tsb' ) }</CardHeader>
					<CardBody>
						<VStack spacing={ 4 }>
							{ num( 'slot_minutes', __( 'Slot length (min)', 'tsb' ), 5, __( 'How long each time slot is.', 'tsb' ) ) }
							{ num( 'slot_offset', __( 'Start offset (min)', 'tsb' ), 0, __( 'Minutes after opening before the first slot.', 'tsb' ) ) }
							{ num( 'slot_gap', __( 'Gap between slots (min)', 'tsb' ), 0, __( 'Buffer between two slots.', 'tsb' ) ) }
							{ num( 'days_ahead', __( 'Days ahead', 'tsb' ), 1 ) }
							{ num( 'lead_hours', __( 'Minimum lead time (hours)', 'tsb' ), 0, __( '0 = bookable right now.', 'tsb' ) ) }
						</VStack>
					</CardBody>
				</Card>

				<Card className="tsb-card">
					<CardHeader>{ __( 'Public holidays', 'tsb' ) }</CardHeader>
					<CardBody>
						<VStack spacing={ 4 }>
							{ tog( 'block_holidays', __( 'Block holidays', 'tsb' ) ) }
							<FormTokenField
								label={ __( 'Countries', 'tsb' ) }
								value={ d.holiday_countries.map( ( c ) => m.countries[ c ] || c ) }
								suggestions={ Object.values( m.countries ) }
								onChange={ ( tokens ) => {
									const nameToCode: Record< string, string > = {};
									Object.keys( m.countries ).forEach( ( code ) => { nameToCode[ m.countries[ code ] ] = code; } );
									const codes = tokens
										.map( ( t ) => ( typeof t === 'string' ? t : t.value ) )
										.map( ( name ) => nameToCode[ name ] )
										.filter( Boolean ) as string[];
									set( 'holiday_countries', codes.length ? codes : [ 'DK' ] );
								} }
								__experimentalExpandOnFocus
								__nextHasNoMarginBottom
							/>
							<p className="tsb-help">{ __( 'Holidays are fetched from date.nager.at (cached).', 'tsb' ) }</p>
						</VStack>
					</CardBody>
				</Card>
			</Grid>
		</VStack>
	);

	/* ---- Form ---- */
	const tabForm = (
		<VStack spacing={ 5 }>
			<Card className="tsb-card">
				<CardHeader>{ __( 'Form fields', 'tsb' ) }</CardHeader>
				<CardBody>
					<p className="tsb-help">{ __( 'Name and email are always shown and required. Add your own fields below — drag to reorder.', 'tsb' ) }</p>
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

	/* ---- Emails ---- */
	const tabEmails = (
		<VStack spacing={ 5 }>
			<EmailEditor
				emails={ d.emails }
				events={ m.emailEvents }
				tokensByEvent={ m.tokensByEvent }
				tokenLabels={ m.tokenLabels }
				sampleVars={ m.sampleVars }
				defaults={ m.emailDefaults }
				adminEmail={ m.adminEmail }
				onChange={ ( emails ) => set( 'emails', emails ) }
			/>
			<Grid columns={ 2 } gap={ 5 } className="tsb-cards-2">
				<Card className="tsb-card">
					<CardHeader>{ __( 'Calendar invite (.ics)', 'tsb' ) }</CardHeader>
					<CardBody><VStack spacing={ 3 }>
						{ tog( 'ics_attach', __( 'Attach .ics to customer email', 'tsb' ) ) }
						<VarMenu tokens={ m.tokensByEvent.confirm || [] } labels={ m.tokenLabels }>
							{ txt( 'ics_summary', __( 'Title', 'tsb' ) ) }
						</VarMenu>
						<p className="tsb-help tsb-varmenu-hint">{ __( 'Right-click the title to insert a variable.', 'tsb' ) }</p>
						{ txt( 'ics_location', __( 'Location', 'tsb' ) ) }
					</VStack></CardBody>
				</Card>
				<Card className="tsb-card">
					<CardHeader>{ __( 'Reminder', 'tsb' ) }</CardHeader>
					<CardBody>
						{ num( 'reminder_hours', __( 'Send reminder this many hours before', 'tsb' ), 1, __( 'Enable the “Reminder” email template above.', 'tsb' ) ) }
					</CardBody>
				</Card>
			</Grid>
		</VStack>
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

	const tabs = [
		{ name: 'availability', title: __( 'Availability', 'tsb' ) },
		{ name: 'form', title: __( 'Form', 'tsb' ) },
		{ name: 'emails', title: __( 'Emails', 'tsb' ) },
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
					if ( tab.name === 'blocks' ) {
						return <Blocks />;
					}
					const body =
						tab.name === 'availability' ? tabAvail :
						tab.name === 'form' ? tabForm :
						tab.name === 'emails' ? tabEmails : tabSpam;
					return (
						<div className="tsb-tab-body">
							{ body }
							<div className="tsb-savebar">
								{ saved !== '' && JSON.stringify( d ) !== saved && (
									<span className="tsb-unsaved">{ __( 'Unsaved changes', 'tsb' ) }</span>
								) }
								<Button variant="primary" isBusy={ saving } disabled={ saved !== '' && JSON.stringify( d ) === saved } onClick={ save } __next40pxDefaultSize>
									{ __( 'Save settings', 'tsb' ) }
								</Button>
							</div>
						</div>
					);
				} }
			</TabPanel>
		</>
	);
}
