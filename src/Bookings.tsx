import {
	Button,
	Spinner,
	Notice,
	SelectControl,
	SearchControl,
	Flex,
	Card,
	CardBody,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api, qs } from './api';
import MoveModal from './MoveModal';
import ConfirmButton from './ConfirmButton';
import type { Booking } from './types';

const PER = 20;

export default function Bookings() {
	const [ data, setData ] = useState< { items: Booking[]; total: number } >( { items: [], total: 0 } );
	const [ loading, setLoading ] = useState( true );
	const [ search, setSearch ] = useState( '' );
	const [ scope, setScope ] = useState< 'upcoming' | 'past' | 'all' >( 'upcoming' );
	const [ page, setPage ] = useState( 1 );
	const [ orderby, setOrderby ] = useState( 'slot_date' );
	const [ order, setOrder ] = useState( 'asc' );
	const [ notice, setNotice ] = useState< { type: 'success' | 'error'; msg: string } | null >( null );
	const [ moving, setMoving ] = useState< Booking | null >( null );

	function load() {
		setLoading( true );
		api< { items: Booking[]; total: number } >(
			'bookings?' + qs( { search, scope, page, per_page: PER, orderby, order } )
		)
			.then( ( r ) => {
				setData( r );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}
	useEffect( load, [ search, scope, page, orderby, order ] );

	function act( id: number, body: Record< string, unknown >, msg: string ) {
		api( 'bookings/' + id, { method: 'POST', data: body } )
			.then( () => {
				setNotice( { type: 'success', msg } );
				load();
			} )
			.catch( ( e: { message?: string } ) => setNotice( { type: 'error', msg: e?.message || __( 'Error', 'tsb' ) } ) );
	}
	function del( id: number ) {
		api( 'bookings/' + id, { method: 'DELETE' } ).then( () => {
			setNotice( { type: 'success', msg: __( 'Booking(s) deleted.', 'tsb' ) } );
			load();
		} );
	}

	function sortHead( key: string, label: string, className = '' ) {
		const active = orderby === key;
		return (
			<th className={ className }>
				<a
					href="#"
					className={ active ? 'is-sorted' : '' }
					onClick={ ( e ) => {
						e.preventDefault();
						setOrderby( key );
						setOrder( active && order === 'asc' ? 'desc' : 'asc' );
					} }
				>
					{ label }
					{ active ? ( order === 'asc' ? ' ▲' : ' ▼' ) : '' }
				</a>
			</th>
		);
	}

	const pages = Math.max( 1, Math.ceil( data.total / PER ) );

	return (
		<>
			<Flex className="tsb-page-head" justify="space-between" align="center">
				<h1>{ __( 'Bookings', 'tsb' ) }</h1>
				<Button variant="secondary" href={ tsbAdmin.exportUrl }>
					{ __( 'Export CSV', 'tsb' ) }
				</Button>
			</Flex>

			{ notice && (
				<Notice status={ notice.type } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

			<Card className="tsb-card">
				<CardBody>
					<Flex className="tsb-toolbar" justify="flex-start" align="flex-end">
						<SelectControl
							label={ __( 'Show', 'tsb' ) }
							value={ scope }
							options={ [
								{ label: __( 'Upcoming', 'tsb' ), value: 'upcoming' },
								{ label: __( 'Past', 'tsb' ), value: 'past' },
								{ label: __( 'All', 'tsb' ), value: 'all' },
							] }
							onChange={ ( v ) => {
								setPage( 1 );
								setScope( v );
							} }
							__nextHasNoMarginBottom
						/>
						<SearchControl
							label={ __( 'Search', 'tsb' ) }
							value={ search }
							onChange={ ( v ) => {
								setPage( 1 );
								setSearch( v );
							} }
							__nextHasNoMarginBottom
						/>
					</Flex>

					{ loading ? (
						<div className="tsb-loading-row"><Spinner /></div>
					) : (
						<table className="wp-list-table widefat striped tsb-table">
							<thead>
								<tr>
									{ sortHead( 'slot_date', __( 'Date', 'tsb' ) ) }
									{ sortHead( 'slot_time', __( 'Time', 'tsb' ) ) }
									{ sortHead( 'type_id', __( 'Type', 'tsb' ), 'tsb-col-type' ) }
									{ sortHead( 'name', __( 'Name', 'tsb' ) ) }
									{ sortHead( 'email', __( 'Email', 'tsb' ) ) }
									{ sortHead( 'phone', __( 'Phone', 'tsb' ) ) }
									{ sortHead( 'status', __( 'Status', 'tsb' ), 'tsb-col-status' ) }
									<th className="tsb-col-actions" />
								</tr>
							</thead>
							<tbody>
								{ data.items.length ? (
									data.items.map( ( b ) => {
										const cancelled = b.status === 'cancelled';
										return (
											<tr key={ b.id } className={ cancelled ? 'is-cancelled' : '' }>
												<td>{ b.slot_date }</td>
												<td>{ b.slot_time }</td>
												<td className="tsb-col-type">
													<span className="tsb-type-tag">{ b.type_label || b.type_id }</span>
												</td>
												<td>
													<strong>{ b.name }</strong>
												</td>
												<td>
													<a href={ 'mailto:' + b.email }>{ b.email }</a>
												</td>
												<td>{ b.phone ? <a href={ 'tel:' + b.phone.replace( /\s+/g, '' ) }>{ b.phone }</a> : '' }</td>
												<td className="tsb-col-status">
													<span className={ 'tsb-status is-' + b.status }>{ b.status }</span>
												</td>
												<td className="tsb-col-actions">
													<div className="tsb-actions-cell">
														<span className="tsb-act">
															{ cancelled ? (
																<Button variant="secondary" size="small" onClick={ () => act( b.id, { op: 'restore' }, __( 'Booking restored.', 'tsb' ) ) }>
																	{ __( 'Restore', 'tsb' ) }
																</Button>
															) : (
																<Button variant="secondary" size="small" onClick={ () => setMoving( b ) }>
																	{ __( 'Move', 'tsb' ) }
																</Button>
															) }
														</span>
														<span className="tsb-act">
															{ ! cancelled && (
																<ConfirmButton
																	variant="tertiary"
																	size="small"
																	isDestructive
																	onConfirm={ () => act( b.id, { op: 'cancel' }, __( 'Booking(s) cancelled. The time is available again.', 'tsb' ) ) }
																>
																	{ __( 'Cancel', 'tsb' ) }
																</ConfirmButton>
															) }
														</span>
														<span className="tsb-act">
															<ConfirmButton variant="tertiary" size="small" isDestructive onConfirm={ () => del( b.id ) }>
																{ __( 'Delete', 'tsb' ) }
															</ConfirmButton>
														</span>
													</div>
												</td>
											</tr>
										);
									} )
								) : (
									<tr>
										<td colSpan={ 8 }>
											<em>{ __( 'No bookings.', 'tsb' ) }</em>
										</td>
									</tr>
								) }
							</tbody>
						</table>
					) }

					{ pages > 1 && (
						<Flex className="tsb-pager" justify="center" align="center">
							<Button variant="secondary" size="small" disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>
								‹
							</Button>
							<span>{ page + ' / ' + pages }</span>
							<Button variant="secondary" size="small" disabled={ page >= pages } onClick={ () => setPage( page + 1 ) }>
								›
							</Button>
						</Flex>
					) }
				</CardBody>
			</Card>

			{ moving && (
				<MoveModal
					booking={ moving }
					onClose={ () => setMoving( null ) }
					onMoved={ () => {
						setMoving( null );
						setNotice( { type: 'success', msg: __( 'Booking moved.', 'tsb' ) } );
						load();
					} }
				/>
			) }
		</>
	);
}
