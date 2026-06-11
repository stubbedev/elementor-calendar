import {
	Card,
	CardBody,
	CardHeader,
	Panel,
	PanelBody,
	ToggleControl,
	TextControl,
	TextareaControl,
	Notice,
	__experimentalNumberControl as NumberControl,
	__experimentalVStack as VStack,
	__experimentalGrid as Grid,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import AvailabilityForm from './AvailabilityForm';
import type { AvailabilityValue } from './AvailabilityForm';
import EmailEditor from './EmailEditor';
import VarMenu from './VarMenu';
import type { SessionType, TypesMeta, EmailTemplate } from './types';

interface Props {
	type: SessionType;
	meta: TypesMeta;
	adminEmail: string;
	onChange: ( patch: Partial< SessionType > ) => void;
}

export default function TypeEditor( { type: t, meta: m, adminEmail, onChange }: Props ) {
	const availKeys: ( keyof AvailabilityValue )[] = [
		'slot_minutes', 'slot_gap', 'base_start', 'base_end', 'week',
	];

	const tabIdentity = (
		<VStack spacing={ 3 } className="tsb-card-narrow">
			<TextControl
				label={ __( 'Name', 'tsb' ) }
				value={ t.label }
				onChange={ ( v ) => onChange( { label: v } ) }
				help={ __( 'Shown to visitors in the type picker.', 'tsb' ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<TextareaControl
				label={ __( 'Description', 'tsb' ) }
				value={ t.description }
				rows={ 2 }
				onChange={ ( v ) => onChange( { description: v } ) }
				help={ __( 'Optional. Shown under the name in the picker.', 'tsb' ) }
				__nextHasNoMarginBottom
			/>
			<ToggleControl
				label={ __( 'Enabled (bookable on the front end)', 'tsb' ) }
				checked={ !! t.enabled }
				onChange={ ( v ) => onChange( { enabled: v ? 1 : 0 } ) }
				__nextHasNoMarginBottom
			/>
		</VStack>
	);

	const tabAvail = (
		<AvailabilityForm
			value={ t as unknown as AvailabilityValue }
			weekdays={ m.weekdays }
			onChange={ ( patch ) => {
				// Only forward the availability-owning keys.
				const out: Partial< SessionType > = {};
				( Object.keys( patch ) as ( keyof AvailabilityValue )[] ).forEach( ( k ) => {
					if ( availKeys.includes( k ) ) {
						( out as Record< string, unknown > )[ k ] = patch[ k ];
					}
				} );
				onChange( out );
			} }
		/>
	);

	const tabEmails = (
		<EmailEditor
			emails={ t.emails }
			events={ m.emailEvents }
			tokensByEvent={ m.tokensByEvent }
			tokenLabels={ m.tokenLabels }
			sampleVars={ m.sampleVars }
			defaults={ m.emailDefaults }
			adminEmail={ adminEmail }
			testType={ t.id }
			onChange={ ( emails: Record< string, EmailTemplate > ) => onChange( { emails } ) }
		/>
	);

	const tabVideo = (
		<VStack spacing={ 5 }>
			<Card className="tsb-card tsb-card-narrow">
				<CardHeader>{ __( 'Video meeting (Google Meet)', 'tsb' ) }</CardHeader>
				<CardBody>
					<VStack spacing={ 3 }>
						<ToggleControl
							label={ __( 'Attach a Google Meet link to this session', 'tsb' ) }
							checked={ !! t.meet_enabled }
							onChange={ ( v ) => onChange( { meet_enabled: v ? 1 : 0 } ) }
							help={ __( 'Creates a Google Calendar event with a Meet link per booking. Available as the {{meet_url}} variable in emails.', 'tsb' ) }
							__nextHasNoMarginBottom
						/>
						{ !! t.meet_enabled && ! m.googleReady && (
							<Notice status="warning" isDismissible={ false }>
								{ __( 'Google Calendar is not connected yet. Connect it under the Google tab for Meet links to be created.', 'tsb' ) }
							</Notice>
						) }
					</VStack>
				</CardBody>
			</Card>

			<Grid columns={ 2 } gap={ 5 } className="tsb-cards-2">
				<Card className="tsb-card">
					<CardHeader>{ __( 'Calendar invite (.ics)', 'tsb' ) }</CardHeader>
					<CardBody><VStack spacing={ 3 }>
						<ToggleControl
							label={ __( 'Attach .ics to customer email', 'tsb' ) }
							checked={ !! t.ics_attach }
							onChange={ ( v ) => onChange( { ics_attach: v ? 1 : 0 } ) }
							__nextHasNoMarginBottom
						/>
						<VarMenu tokens={ m.tokensByEvent.confirm || [] } labels={ m.tokenLabels }>
							<TextControl label={ __( 'Title', 'tsb' ) } value={ t.ics_summary } onChange={ ( v ) => onChange( { ics_summary: v } ) } __nextHasNoMarginBottom __next40pxDefaultSize />
						</VarMenu>
						<p className="tsb-help tsb-varmenu-hint">{ __( 'Right-click the title to insert a variable.', 'tsb' ) }</p>
						<TextControl label={ __( 'Location', 'tsb' ) } value={ t.ics_location } onChange={ ( v ) => onChange( { ics_location: v } ) } __nextHasNoMarginBottom __next40pxDefaultSize />
					</VStack></CardBody>
				</Card>
				<Card className="tsb-card">
					<CardHeader>{ __( 'Reminder', 'tsb' ) }</CardHeader>
					<CardBody>
						<NumberControl
							label={ __( 'Send reminder this many hours before', 'tsb' ) }
							min={ 1 }
							value={ String( t.reminder_hours ) }
							onChange={ ( v?: string ) => onChange( { reminder_hours: v === '' || v == null ? 1 : parseInt( v, 10 ) } ) }
							help={ __( 'Enable the “Reminder” email template under Emails.', 'tsb' ) }
							__next40pxDefaultSize
						/>
					</CardBody>
				</Card>
			</Grid>
		</VStack>
	);

	return (
		<Panel className="tsb-type-panel">
			<PanelBody title={ __( 'Session', 'tsb' ) } initialOpen={ true }>
				{ tabIdentity }
			</PanelBody>
			<PanelBody title={ __( 'Schedule', 'tsb' ) } initialOpen={ false }>
				{ tabAvail }
			</PanelBody>
			<PanelBody title={ __( 'Emails', 'tsb' ) } initialOpen={ false }>
				{ tabEmails }
			</PanelBody>
			<PanelBody title={ __( 'Video & invite', 'tsb' ) } initialOpen={ false }>
				{ tabVideo }
			</PanelBody>
		</Panel>
	);
}
