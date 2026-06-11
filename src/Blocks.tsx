import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Notice,
	ToggleControl,
	SelectControl,
	Flex,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api, qs } from './api';
import MiniCalendar, { type DayStatus } from './MiniCalendar';
import type { Block } from './types';

function pad( n: number ) {
	return ( n < 10 ? '0' : '' ) + n;
}
function todayISO() {
	const d = new Date();
	return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
}
function humanDate( iso: string, opts: Intl.DateTimeFormatOptions ) {
	const [ y, m, d ] = iso.split( '-' ).map( Number );
	return new Date( y, m - 1, d ).toLocaleDateString( undefined, opts );
}

// 15-minute time options for the range pickers.
const TIME_OPTIONS = ( () => {
	const out: { label: string; value: string }[] = [];
	for ( let h = 0; h < 24; h++ ) {
		for ( const mm of [ 0, 15, 30, 45 ] ) {
			const v = pad( h ) + ':' + pad( mm );
			out.push( { label: v, value: v } );
		}
	}
	return out;
} )();

const PRESETS = [
	{ label: __( 'Morning', 'tsb' ), from: '09:00', to: '12:00' },
	{ label: __( 'Lunch', 'tsb' ), from: '12:00', to: '13:00' },
	{ label: __( 'Afternoon', 'tsb' ), from: '13:00', to: '17:00' },
];

export default function Blocks() {
	const init = todayISO();
	const [ view, setView ] = useState( { y: Number( init.slice( 0, 4 ) ), m: Number( init.slice( 5, 7 ) ) - 1 } );
	const [ date, setDate ] = useState( init );
	const [ monthDays, setMonthDays ] = useState< Record< string, DayStatus > >( {} );
	const [ blocks, setBlocks ] = useState< Block[] >( [] );
	const [ from, setFrom ] = useState( '09:00' );
	const [ to, setTo ] = useState( '12:00' );
	const [ error, setError ] = useState( '' );

	function loadBlocks() {
		api< { items: Block[] } >( 'blocks' ).then( ( r ) => setBlocks( r.items || [] ) );
	}
	function loadMonth() {
		api< { days: Record< string, DayStatus > } >( 'month?' + qs( { year: view.y, month: view.m + 1 } ) )
			.then( ( r ) => setMonthDays( r.days || {} ) );
	}
	useEffect( loadBlocks, [] );
	useEffect( loadMonth, [ view.y, view.m ] );

	function refresh() {
		loadBlocks();
		loadMonth();
	}
	function addRange() {
		setError( '' );
		if ( to <= from ) {
			setError( __( 'End time must be after the start time.', 'tsb' ) );
			return;
		}
		api( 'blocks', { method: 'POST', data: { block_date: date, block_time: from, block_end: to } } )
			.then( refresh )
			.catch( ( e: { message?: string } ) => setError( e?.message || __( 'Could not add the time off.', 'tsb' ) ) );
	}
	function delBlock( id: number ) {
		api( 'blocks/' + id, { method: 'DELETE' } ).then( refresh );
	}
	function toggleWholeDay( on: boolean ) {
		if ( on ) {
			api( 'blocks', { method: 'POST', data: { block_date: date } } ).then( refresh );
		} else if ( wholeDay ) {
			delBlock( wholeDay.id );
		}
	}

	function nav( delta: number ) {
		let m = view.m + delta;
		let y = view.y;
		if ( m < 0 ) { m = 11; y--; }
		if ( m > 11 ) { m = 0; y++; }
		setView( { y, m } );
	}

	const wholeDay = blocks.find( ( b ) => b.block_date === date && ! b.block_time );
	const dayRanges = blocks
		.filter( ( b ) => b.block_date === date && b.block_time )
		.sort( ( a, b ) => a.block_time.localeCompare( b.block_time ) );
	// "closed" = the weekday is off or it's a holiday (server-derived, not a manual block).
	const isClosed = ( monthDays[ date ]?.status ) === 'closed';

	const rangeLabel = ( b: Block ) =>
		b.block_time ? b.block_time + ' – ' + b.block_end : __( 'All day', 'tsb' );

	// Recolour the calendar around *closures* (not booking load): a day with manual
	// ranges reads as "time off", a whole-day block as "closed all day", weekday-off /
	// holidays as "closed", everything else as plain "open".
	const calDays: Record< string, DayStatus > = {};
	const byDate: Record< string, { whole: boolean; ranges: boolean } > = {};
	blocks.forEach( ( b ) => {
		const e = byDate[ b.block_date ] || { whole: false, ranges: false };
		if ( b.block_time ) { e.ranges = true; } else { e.whole = true; }
		byDate[ b.block_date ] = e;
	} );
	const monthKey = view.y + '-' + pad( view.m + 1 );
	Object.keys( monthDays ).forEach( ( k ) => { calDays[ k ] = monthDays[ k ]; } );
	// Ensure every block in this month is reflected even if month() hasn't loaded it.
	Object.keys( byDate ).forEach( ( k ) => {
		if ( ! k.startsWith( monthKey ) ) { return; }
		const base = monthDays[ k ]?.status;
		if ( base === 'closed' ) { return; }
		if ( byDate[ k ].whole ) { calDays[ k ] = { status: 'wholeday' }; }
		else if ( byDate[ k ].ranges ) { calDays[ k ] = { status: 'partial' }; }
	} );
	// Drop booking-load colouring: anything not closed/off/wholeday is just "open".
	Object.keys( calDays ).forEach( ( k ) => {
		const s = calDays[ k ].status;
		if ( s === 'full' || s === 'free' ) { calDays[ k ] = { status: 'free' }; }
	} );

	const upcoming = blocks
		.filter( ( b ) => b.block_date >= init )
		.sort( ( a, b ) =>
			a.block_date === b.block_date
				? ( a.block_time || '' ).localeCompare( b.block_time || '' )
				: a.block_date.localeCompare( b.block_date )
		);

	return (
		<Card className="tsb-card">
			<CardHeader>{ __( 'Time off', 'tsb' ) }</CardHeader>
			<CardBody>
				<p className="tsb-help">{ __( 'Close specific dates or time ranges across every session type — holidays, breaks, days off. Pick a day, then close it fully or add time ranges.', 'tsb' ) }</p>

				<div className="tsb-timeoff">
					<div className="tsb-timeoff-cal">
						<MiniCalendar view={ view } selected={ date } days={ calDays } onSelect={ setDate } onNav={ nav } />
						<p className="tsb-legend tsb-legend-cal">
							<span className="tsb-legend-dot is-open" /> { __( 'Open', 'tsb' ) }
							<span className="tsb-legend-dot is-partial" /> { __( 'Time off', 'tsb' ) }
							<span className="tsb-legend-dot is-full" /> { __( 'Closed all day', 'tsb' ) }
							<span className="tsb-legend-dot is-closed" /> { __( 'Closed (weekday/holiday)', 'tsb' ) }
						</p>
					</div>

					<div className="tsb-timeoff-panel">
						<h3 className="tsb-timeoff-date">{ humanDate( date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' } ) }</h3>

						{ isClosed && ! wholeDay ? (
							<Notice status="info" isDismissible={ false }>
								{ __( 'This day is already closed (weekday off or holiday). No time off needed.', 'tsb' ) }
							</Notice>
						) : (
							<>
								<ToggleControl
									label={ __( 'Closed all day', 'tsb' ) }
									checked={ !! wholeDay }
									onChange={ toggleWholeDay }
									__nextHasNoMarginBottom
								/>

								{ ! wholeDay && (
									<>
										{ dayRanges.length > 0 ? (
											<ul className="tsb-range-list">
												{ dayRanges.map( ( b ) => (
													<li key={ b.id }>
														<span className="tsb-range-time">{ rangeLabel( b ) }</span>
														<Button variant="tertiary" size="small" isDestructive icon="no-alt" label={ __( 'Remove', 'tsb' ) } onClick={ () => delBlock( b.id ) } />
													</li>
												) ) }
											</ul>
										) : (
											<p className="tsb-timeoff-empty">{ __( 'No time off on this day yet.', 'tsb' ) }</p>
										) }

										<div className="tsb-range-add">
											<Flex justify="flex-start" align="flex-end" gap={ 2 } wrap>
												<SelectControl
													label={ __( 'From', 'tsb' ) }
													value={ from }
													options={ TIME_OPTIONS }
													onChange={ ( v ) => setFrom( v ) }
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<SelectControl
													label={ __( 'To', 'tsb' ) }
													value={ to }
													options={ TIME_OPTIONS }
													onChange={ ( v ) => setTo( v ) }
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<Button variant="secondary" icon="plus" onClick={ addRange } __next40pxDefaultSize>
													{ __( 'Add', 'tsb' ) }
												</Button>
											</Flex>
											<div className="tsb-range-presets">
												<span className="tsb-range-presets-label">{ __( 'Quick:', 'tsb' ) }</span>
												{ PRESETS.map( ( p ) => (
													<Button key={ p.label } variant="tertiary" size="small" onClick={ () => { setFrom( p.from ); setTo( p.to ); } }>
														{ p.label }
													</Button>
												) ) }
											</div>
											{ error && (
												<Notice status="error" isDismissible onRemove={ () => setError( '' ) }>
													{ error }
												</Notice>
											) }
										</div>
									</>
								) }
							</>
						) }
					</div>
				</div>

				<div className="tsb-timeoff-upcoming">
					<h3>{ __( 'Upcoming time off', 'tsb' ) }</h3>
					{ upcoming.length ? (
						<ul className="tsb-upcoming-list">
							{ upcoming.map( ( b ) => (
								<li key={ b.id }>
									<button
										type="button"
										className="tsb-upcoming-date"
										onClick={ () => {
											setView( { y: Number( b.block_date.slice( 0, 4 ) ), m: Number( b.block_date.slice( 5, 7 ) ) - 1 } );
											setDate( b.block_date );
										} }
									>
										{ humanDate( b.block_date, { weekday: 'short', day: 'numeric', month: 'short' } ) }
									</button>
									<span className={ 'tsb-upcoming-when' + ( b.block_time ? '' : ' is-allday' ) }>{ rangeLabel( b ) }</span>
									<Button variant="tertiary" size="small" isDestructive icon="trash" label={ __( 'Delete', 'tsb' ) } onClick={ () => delBlock( b.id ) } />
								</li>
							) ) }
						</ul>
					) : (
						<p className="tsb-timeoff-empty">{ __( 'No upcoming time off.', 'tsb' ) }</p>
					) }
				</div>
			</CardBody>
		</Card>
	);
}
