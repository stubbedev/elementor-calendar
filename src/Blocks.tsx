import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Notice,
	ToggleControl,
	TextControl,
	Flex,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api, qs } from './api';
import MiniCalendar, { type DayStatus } from './MiniCalendar';
import type { Block } from './types';

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
	const [ blocks, setBlocks ] = useState< Block[] >( [] );
	const [ from, setFrom ] = useState( '09:00' );
	const [ to, setTo ] = useState( '10:00' );
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
			.catch( ( e: { message?: string } ) => setError( e?.message || __( 'Could not add the block.', 'tsb' ) ) );
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

	const rangeLabel = ( b: Block ) =>
		b.block_time ? b.block_time + '–' + b.block_end : __( 'Whole day', 'tsb' );

	return (
		<VStack spacing={ 5 }>
			<Card className="tsb-card">
				<CardHeader>{ __( 'Time off', 'tsb' ) }</CardHeader>
				<CardBody>
					<p className="tsb-help">{ __( 'Block dates or time ranges across every session type — holidays, lunch breaks, days off.', 'tsb' ) }</p>
					<div className="tsb-daypick">
						<div className="tsb-daypick-cal">
							<MiniCalendar view={ view } selected={ date } days={ monthDays } onSelect={ setDate } onNav={ nav } />
							<p className="tsb-legend tsb-legend-cal">
								<span className="tsb-legend-dot is-free" /> { __( 'Free', 'tsb' ) }
								<span className="tsb-legend-dot is-partial" /> { __( 'Some blocked', 'tsb' ) }
								<span className="tsb-legend-dot is-full" /> { __( 'Fully blocked', 'tsb' ) }
								<span className="tsb-legend-dot is-closed" /> { __( 'Closed', 'tsb' ) }
							</p>
						</div>
						<div className="tsb-daypick-panel">
							<h3 className="tsb-daypick-date">{ date }</h3>
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
							) : (
								<>
									{ dayRanges.length > 0 && (
										<ul className="tsb-range-list">
											{ dayRanges.map( ( b ) => (
												<li key={ b.id }>
													<span>{ rangeLabel( b ) }</span>
													<Button variant="tertiary" size="small" isDestructive onClick={ () => delBlock( b.id ) }>
														{ __( 'Remove', 'tsb' ) }
													</Button>
												</li>
											) ) }
										</ul>
									) }
									<Flex className="tsb-range-add" justify="flex-start" align="flex-end" gap={ 3 }>
										<TextControl
											label={ __( 'From', 'tsb' ) }
											type="time"
											value={ from }
											onChange={ ( v ) => setFrom( v ) }
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
										<TextControl
											label={ __( 'To', 'tsb' ) }
											type="time"
											value={ to }
											onChange={ ( v ) => setTo( v ) }
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
										<Button variant="secondary" onClick={ addRange } __next40pxDefaultSize>
											{ __( 'Block range', 'tsb' ) }
										</Button>
									</Flex>
									{ error && (
										<Notice status="error" isDismissible onRemove={ () => setError( '' ) }>
											{ error }
										</Notice>
									) }
								</>
							) }
						</div>
					</div>
				</CardBody>
			</Card>

			<Card className="tsb-card tsb-card-narrow">
				<CardHeader>{ __( 'All time off', 'tsb' ) }</CardHeader>
				<CardBody>
					<table className="wp-list-table widefat striped tsb-table">
						<thead>
							<tr>
								<th>{ __( 'Date', 'tsb' ) }</th>
								<th>{ __( 'When', 'tsb' ) }</th>
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
										<td>{ b.block_time ? rangeLabel( b ) : <em>{ __( 'Whole day', 'tsb' ) }</em> }</td>
										<td className="tsb-col-actions">
											<Button variant="tertiary" size="small" isDestructive onClick={ () => delBlock( b.id ) }>
												{ __( 'Delete', 'tsb' ) }
											</Button>
										</td>
									</tr>
								) )
							) : (
								<tr>
									<td colSpan={ 3 }>
										<em>{ __( 'No time off.', 'tsb' ) }</em>
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
