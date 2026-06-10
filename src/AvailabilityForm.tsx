import {
	Card,
	CardBody,
	CardHeader,
	ToggleControl,
	SelectControl,
	FormTokenField,
	__experimentalNumberControl as NumberControl,
	__experimentalVStack as VStack,
	__experimentalGrid as Grid,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { WeekDay } from './types';

/** The availability-owning subset of a session type. */
export interface AvailabilityValue {
	slot_minutes: number;
	slot_offset: number;
	slot_gap: number;
	base_start: number;
	base_end: number;
	days_ahead: number;
	lead_hours: number;
	block_holidays: number;
	holiday_countries: string[];
	week: Record< string, WeekDay >;
}

interface Props {
	value: AvailabilityValue;
	weekdays: Record< string, string >;
	countries: Record< string, string >;
	onChange: ( patch: Partial< AvailabilityValue > ) => void;
}

function hourOptions( from: number, to: number ) {
	const out = [];
	for ( let h = from; h <= to; h++ ) {
		out.push( { label: ( h < 10 ? '0' + h : String( h ) ) + ':00', value: String( h ) } );
	}
	return out;
}

export default function AvailabilityForm( { value: d, weekdays, countries, onChange }: Props ) {
	function setWeek( day: string, k: keyof WeekDay, v: number ) {
		onChange( { week: { ...d.week, [ day ]: { ...d.week[ day ], [ k ]: v } } } );
	}

	const num = ( k: keyof AvailabilityValue, label: string, min = 0, help?: string ) => (
		<NumberControl
			label={ label }
			min={ min }
			value={ String( d[ k ] ) }
			help={ help }
			onChange={ ( v?: string ) => onChange( { [ k ]: v === '' || v == null ? 0 : parseInt( v, 10 ) } as Partial< AvailabilityValue > ) }
			__next40pxDefaultSize
		/>
	);
	const tog = ( k: keyof AvailabilityValue, label: string ) => (
		<ToggleControl label={ label } checked={ !! d[ k ] } onChange={ ( v ) => onChange( { [ k ]: v ? 1 : 0 } as Partial< AvailabilityValue > ) } __nextHasNoMarginBottom />
	);
	const hour = ( val: number, on: ( n: number ) => void, from: number, to: number, label?: string, disabled?: boolean ) => (
		<SelectControl label={ label } value={ String( val ) } options={ hourOptions( from, to ) } disabled={ disabled } onChange={ ( v ) => on( parseInt( v, 10 ) ) } __nextHasNoMarginBottom __next40pxDefaultSize />
	);

	return (
		<VStack spacing={ 5 }>
			<Card className="tsb-card">
				<CardHeader>{ __( 'Base business hours', 'tsb' ) }</CardHeader>
				<CardBody>
					<Grid columns={ 2 } gap={ 4 } className="tsb-hours-grid">
						{ hour( d.base_start, ( n ) => onChange( { base_start: n } ), 0, 23, __( 'From', 'tsb' ) ) }
						{ hour( d.base_end, ( n ) => onChange( { base_end: n } ), 1, 24, __( 'To', 'tsb' ) ) }
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
									<span className="tsb-week-day">{ weekdays[ day ] }</span>
									<ToggleControl
										label={ weekdays[ day ] + ' — ' + __( 'Open', 'tsb' ) }
										checked={ !! w.open }
										onChange={ ( v ) => setWeek( day, 'open', v ? 1 : 0 ) }
										className="tsb-toggle-bare"
										__nextHasNoMarginBottom
									/>
									<ToggleControl
										label={ weekdays[ day ] + ' — ' + __( 'Follow base', 'tsb' ) }
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
								value={ d.holiday_countries.map( ( c ) => countries[ c ] || c ) }
								suggestions={ Object.values( countries ) }
								onChange={ ( tokens ) => {
									const nameToCode: Record< string, string > = {};
									Object.keys( countries ).forEach( ( code ) => { nameToCode[ countries[ code ] ] = code; } );
									const codes = tokens
										.map( ( t ) => ( typeof t === 'string' ? t : t.value ) )
										.map( ( name ) => nameToCode[ name ] || name )
										.filter( Boolean ) as string[];
									onChange( { holiday_countries: codes.length ? codes : [ 'DK' ] } );
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
}
