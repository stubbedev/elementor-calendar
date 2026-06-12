import {
	Modal,
	Button,
	Spinner,
	Notice,
	SelectControl,
	Flex,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api, qs } from './api';
import MiniCalendar, { type DayStatus } from './MiniCalendar';
import type { Booking, Slot, SessionType } from './types';

interface Props {
	booking: Booking;
	onClose: () => void;
	onMoved: () => void;
}

export default function MoveModal( { booking, onClose, onMoved }: Props ) {
	const [ date, setDate ] = useState( booking.slot_date );
	const [ time, setTime ] = useState( booking.slot_time );
	const [ type, setType ] = useState( booking.type_id );
	const [ types, setTypes ] = useState< SessionType[] >( [] );
	const [ slots, setSlots ] = useState< Slot[] >( [] );
	const [ loading, setLoading ] = useState( false );
	const [ err, setErr ] = useState( '' );
	const [ view, setView ] = useState( {
		y: Number( booking.slot_date.slice( 0, 4 ) ),
		m: Number( booking.slot_date.slice( 5, 7 ) ) - 1,
	} );
	const [ monthDays, setMonthDays ] = useState< Record< string, DayStatus > >( {} );

	useEffect( () => {
		api< { types: SessionType[] } >( 'types' ).then( ( r ) => setTypes( r.types || [] ) );
	}, [] );

	useEffect( () => {
		api< { days: Record< string, DayStatus > } >( 'month?' + qs( { year: view.y, month: view.m + 1, type } ) )
			.then( ( r ) => setMonthDays( r.days || {} ) );
	}, [ view.y, view.m, type ] );

	function nav( delta: number ) {
		let m = view.m + delta;
		let y = view.y;
		if ( m < 0 ) { m = 11; y--; }
		if ( m > 11 ) { m = 0; y++; }
		setView( { y, m } );
	}

	useEffect( () => {
		if ( ! /^\d{4}-\d{2}-\d{2}$/.test( date ) ) {
			setSlots( [] );
			return;
		}
		setLoading( true );
		api< { slots: Slot[] } >( 'availability?' + qs( { date, exclude: booking.id, type } ) )
			.then( ( r ) => {
				setSlots( r.slots || [] );
				setLoading( false );
			} )
			.catch( () => {
				setSlots( [] );
				setLoading( false );
			} );
	}, [ date, type ] );

	function save() {
		setErr( '' );
		api( 'bookings/' + booking.id, { method: 'POST', data: { op: 'move', date, time, type } } )
			.then( onMoved )
			.catch( ( e: { message?: string } ) => setErr( e?.message || __( 'Error', 'tsb' ) ) );
	}

	const typeLabel = ( id: string ) => types.find( ( t ) => t.id === id )?.label || id;
	const chosen = slots.find( ( s ) => s.time === time );
	const blocked = chosen && ! chosen.available;
	const changed =
		date !== booking.slot_date || time !== booking.slot_time || type !== booking.type_id;

	return (
		<Modal
			title={ __( 'Edit booking', 'tsb' ) }
			onRequestClose={ onClose }
			className="tsb-move-modal"
			size="large"
		>
			<p className="tsb-move-current">
				<strong>{ booking.name }</strong>
				{ ' — ' }
				<span className="tsb-pill">{ typeLabel( booking.type_id ) } · { booking.slot_date } { booking.slot_time }</span>
				{ changed && (
					<>
						{ ' → ' }
						<span className="tsb-pill tsb-pill-accent">{ typeLabel( type ) } · { date } { time }</span>
					</>
				) }
			</p>

			{ types.length > 1 && (
				<div className="tsb-edit-type">
					<SelectControl
						label={ __( 'Session type', 'tsb' ) }
						value={ type }
						options={ types.map( ( t ) => ( { label: t.label + ' (' + t.slot_minutes + ' ' + __( 'min', 'tsb' ) + ')', value: t.id } ) ) }
						onChange={ ( v ) => setType( v ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
			) }

			<div className="tsb-move-grid">
				<div className="tsb-move-cal">
					<MiniCalendar view={ view } selected={ date } days={ monthDays } onSelect={ setDate } onNav={ nav } />
				</div>
				<div className="tsb-move-slots">
					<h3>{ __( 'Available times', 'tsb' ) }</h3>
					{ loading ? (
						<Spinner />
					) : slots.length ? (
						<>
							<div className="tsb-slot-grid">
								{ slots.map( ( s ) => (
									<Button
										key={ s.time }
										variant={ time === s.time ? 'primary' : 'secondary' }
										className={ 'tsb-slot-btn' + ( s.available ? '' : ' is-taken' ) }
										disabled={ ! s.available }
										aria-pressed={ time === s.time }
										onClick={ () => setTime( s.time ) }
									>
										{ s.time }
									</Button>
								) ) }
							</div>
							<p className="tsb-legend">
								<span className="tsb-legend-dot is-free" /> { __( 'Free', 'tsb' ) }
								<span className="tsb-legend-dot is-taken" /> { __( 'Taken', 'tsb' ) }
							</p>
						</>
					) : (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'No open times on this day (closed, holiday, or fully blocked).', 'tsb' ) }
						</Notice>
					) }
				</div>
			</div>

			{ blocked && (
				<Notice status="error" isDismissible={ false }>
					{ __( 'That time is already taken. Choose another.', 'tsb' ) }
				</Notice>
			) }
			{ err && (
				<Notice status="error" isDismissible={ false }>
					{ err }
				</Notice>
			) }

			<Flex justify="flex-end" className="tsb-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'tsb' ) }
				</Button>
				<Button variant="primary" disabled={ !! blocked || ! time || ! changed } onClick={ save }>
					{ __( 'Save changes', 'tsb' ) }
				</Button>
			</Flex>
		</Modal>
	);
}
