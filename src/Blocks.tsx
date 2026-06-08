import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Spinner,
	Notice,
	ToggleControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api, qs } from './api';
import MiniCalendar, { type DayStatus } from './MiniCalendar';
import type { Slot, Block } from './types';

function todayISO() {
	const d = new Date();
	const p = ( n: number ) => ( n < 10 ? '0' + n : String( n ) );
	return d.getFullYear() + '-' + p( d.getMonth() + 1 ) + '-' + p( d.getDate() );
}

export default function Blocks() {
	const init = todayISO();
	const [ view, setView ] = useState( { y: Number( init.slice( 0, 4 ) ), m: Number( init.slice( 5, 7 ) ) - 1 } );
	const [ date, setDate ] = useState( init );
	const [ monthDays, setMonthDays ] = useState< Record< string, DayStatus > >( {} );
	const [ slots, setSlots ] = useState< Slot[] >( [] );
	const [ blocks, setBlocks ] = useState< Block[] >( [] );
	const [ loading, setLoading ] = useState( false );

	function loadBlocks() {
		api< { items: Block[] } >( 'blocks' ).then( ( r ) => setBlocks( r.items || [] ) );
	}
	function loadMonth() {
		api< { days: Record< string, DayStatus > } >( 'month?' + qs( { year: view.y, month: view.m + 1 } ) )
			.then( ( r ) => setMonthDays( r.days || {} ) );
	}
	function loadDay() {
		if ( ! /^\d{4}-\d{2}-\d{2}$/.test( date ) ) {
			return;
		}
		setLoading( true );
		api< { slots: Slot[] } >( 'availability?' + qs( { date } ) )
			.then( ( r ) => {
				setSlots( r.slots || [] );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}
	useEffect( loadBlocks, [] );
	useEffect( loadMonth, [ view.y, view.m ] );
	useEffect( loadDay, [ date ] );

	function refresh() {
		loadBlocks();
		loadMonth();
		loadDay();
	}
	function addBlock( time: string ) {
		api( 'blocks', { method: 'POST', data: { block_date: date, block_time: time } } ).then( refresh );
	}
	function delBlock( id: number ) {
		api( 'blocks/' + id, { method: 'DELETE' } ).then( refresh );
	}

	const blockAt = ( time: string ) => blocks.find( ( b ) => b.block_date === date && b.block_time === time );
	const wholeDay = blocks.find( ( b ) => b.block_date === date && ! b.block_time );

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

	return (
		<VStack spacing={ 5 }>
			<Card className="tsb-card">
				<CardHeader>{ __( 'Block times', 'tsb' ) }</CardHeader>
				<CardBody>
					<div className="tsb-daypick">
						<div className="tsb-daypick-cal">
							<MiniCalendar view={ view } selected={ date } days={ monthDays } onSelect={ setDate } onNav={ nav } />
							<p className="tsb-legend tsb-legend-cal">
								<span className="tsb-legend-dot is-free" /> { __( 'Free', 'tsb' ) }
								<span className="tsb-legend-dot is-partial" /> { __( 'Some taken', 'tsb' ) }
								<span className="tsb-legend-dot is-full" /> { __( 'Fully blocked', 'tsb' ) }
								<span className="tsb-legend-dot is-closed" /> { __( 'Closed', 'tsb' ) }
							</p>
						</div>
						<div className="tsb-daypick-panel">
							<ToggleControl
								label={ __( 'Block the whole day', 'tsb' ) }
								checked={ !! wholeDay }
								onChange={ toggleWholeDay }
								__nextHasNoMarginBottom
							/>
							{ wholeDay ? (
								<Notice status="warning" isDismissible={ false }>
									{ __( 'The whole day is blocked.', 'tsb' ) }
								</Notice>
							) : loading ? (
								<Spinner />
							) : slots.length ? (
								<>
									<p className="tsb-help">{ __( 'Click a time to block or unblock it.', 'tsb' ) }</p>
									<div className="tsb-slot-grid">
										{ slots.map( ( s ) => {
											const booked = s.reason === 'booked';
											const isBlocked = !! blockAt( s.time );
											return (
												<Button
													key={ s.time }
													variant={ isBlocked ? 'primary' : 'secondary' }
													className={ 'tsb-slot-btn' + ( isBlocked ? ' is-blocked' : '' ) }
													disabled={ booked }
													title={ booked ? __( 'Booked', 'tsb' ) : '' }
													onClick={ () => {
														const b = blockAt( s.time );
														if ( b ) {
															delBlock( b.id );
														} else {
															addBlock( s.time );
														}
													} }
												>
													{ s.time }
												</Button>
											);
										} ) }
									</div>
									<p className="tsb-legend">
										<span className="tsb-legend-dot is-free" /> { __( 'Free', 'tsb' ) }
										<span className="tsb-legend-dot is-blocked" /> { __( 'Blocked', 'tsb' ) }
										<span className="tsb-legend-dot is-booked" /> { __( 'Booked', 'tsb' ) }
									</p>
								</>
							) : (
								<Notice status="info" isDismissible={ false }>
									{ __( 'No open times on this day (closed weekday or holiday).', 'tsb' ) }
								</Notice>
							) }
						</div>
					</div>
				</CardBody>
			</Card>

			<Card className="tsb-card tsb-card-narrow">
				<CardHeader>{ __( 'All blocks', 'tsb' ) }</CardHeader>
				<CardBody>
					<table className="wp-list-table widefat striped tsb-table">
						<thead>
							<tr>
								<th>{ __( 'Date', 'tsb' ) }</th>
								<th>{ __( 'Time', 'tsb' ) }</th>
								<th>{ __( 'Reason', 'tsb' ) }</th>
								<th className="tsb-col-actions" />
							</tr>
						</thead>
						<tbody>
							{ blocks.length ? (
								blocks.map( ( b ) => (
									<tr key={ b.id }>
										<td>
											<a href="#" onClick={ ( e ) => {
												e.preventDefault();
												setView( { y: Number( b.block_date.slice( 0, 4 ) ), m: Number( b.block_date.slice( 5, 7 ) ) - 1 } );
												setDate( b.block_date );
											} }>{ b.block_date }</a>
										</td>
										<td>{ b.block_time || <em>{ __( 'whole day', 'tsb' ) }</em> }</td>
										<td>{ b.reason }</td>
										<td className="tsb-col-actions">
											<Button variant="tertiary" size="small" isDestructive onClick={ () => delBlock( b.id ) }>
												{ __( 'Delete', 'tsb' ) }
											</Button>
										</td>
									</tr>
								) )
							) : (
								<tr>
									<td colSpan={ 4 }>
										<em>{ __( 'No blocks.', 'tsb' ) }</em>
									</td>
								</tr>
							) }
						</tbody>
					</table>
				</CardBody>
			</Card>
		</VStack>
	);
}
