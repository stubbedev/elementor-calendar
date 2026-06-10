import {
	Button,
	Spinner,
	Notice,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';
import TypeEditor from './TypeEditor';
import type { SessionType, TypesMeta } from './types';

export default function TypeManager() {
	const [ types, setTypes ] = useState< SessionType[] | null >( null );
	const [ meta, setMeta ] = useState< TypesMeta | null >( null );
	const [ sel, setSel ] = useState( 0 );
	const [ saved, setSaved ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState< { type: 'success' | 'error'; msg: string } | null >( null );

	useEffect( () => {
		api< { types: SessionType[]; meta: TypesMeta } >( 'types' ).then( ( r ) => {
			setTypes( r.types );
			setMeta( r.meta );
			setSaved( JSON.stringify( r.types ) );
		} );
	}, [] );

	if ( ! types || ! meta ) {
		return <div className="tsb-loading-row"><Spinner /></div>;
	}
	const m = meta;
	const dirty = JSON.stringify( types ) !== saved;

	function patchType( idx: number, patch: Partial< SessionType > ) {
		setTypes( ( prev ) => ( prev ? prev.map( ( t, i ) => ( i === idx ? { ...t, ...patch } : t ) ) : prev ) );
	}

	function blankFrom( base: SessionType | undefined ): SessionType {
		const avail = base
			? {
				slot_minutes: base.slot_minutes, slot_offset: base.slot_offset, slot_gap: base.slot_gap,
				base_start: base.base_start, base_end: base.base_end, days_ahead: base.days_ahead,
				lead_hours: base.lead_hours, block_holidays: base.block_holidays,
				holiday_countries: [ ...base.holiday_countries ], week: JSON.parse( JSON.stringify( base.week ) ),
			}
			: {
				slot_minutes: 30, slot_offset: 0, slot_gap: 0, base_start: 9, base_end: 17,
				days_ahead: 30, lead_hours: 0, block_holidays: 1, holiday_countries: [ 'DK' ],
				week: Object.fromEntries( [ 1, 2, 3, 4, 5, 6, 7 ].map( ( d ) => [ d, { open: d <= 5 ? 1 : 0, use_base: 1, start: 9, end: 17 } ] ) ),
			};
		return {
			id: '', label: __( 'New session', 'tsb' ), enabled: 1, order: ( types?.length ?? 0 ), description: '',
			...avail,
			emails: JSON.parse( JSON.stringify( m.emailDefaults ) ),
			reminder_hours: 24, ics_attach: 1, ics_summary: 'Booking: {{name}}', ics_location: '', meet_enabled: 0,
		};
	}

	function addType() {
		setTypes( ( prev ) => {
			const next = [ ...( prev || [] ), blankFrom( ( prev || [] )[ 0 ] ) ];
			setSel( next.length - 1 );
			return next;
		} );
	}

	function duplicate( idx: number ) {
		setTypes( ( prev ) => {
			if ( ! prev ) { return prev; }
			const copy = JSON.parse( JSON.stringify( prev[ idx ] ) ) as SessionType;
			copy.id = '';
			copy.label = prev[ idx ].label + ' ' + __( '(copy)', 'tsb' );
			const next = [ ...prev.slice( 0, idx + 1 ), copy, ...prev.slice( idx + 1 ) ];
			setSel( idx + 1 );
			return next;
		} );
	}

	function remove( idx: number ) {
		setTypes( ( prev ) => {
			if ( ! prev || prev.length <= 1 ) { return prev; }
			const next = prev.filter( ( _, i ) => i !== idx );
			setSel( ( s ) => Math.max( 0, Math.min( s, next.length - 1 ) ) );
			return next;
		} );
	}

	function move( idx: number, dir: -1 | 1 ) {
		setTypes( ( prev ) => {
			if ( ! prev ) { return prev; }
			const j = idx + dir;
			if ( j < 0 || j >= prev.length ) { return prev; }
			const next = [ ...prev ];
			[ next[ idx ], next[ j ] ] = [ next[ j ], next[ idx ] ];
			setSel( j );
			return next;
		} );
	}

	function save() {
		setSaving( true );
		api< { types: SessionType[] } >( 'types', { method: 'POST', data: { types } } )
			.then( ( r ) => {
				setTypes( r.types );
				setSaved( JSON.stringify( r.types ) );
				setSel( ( s ) => Math.max( 0, Math.min( s, r.types.length - 1 ) ) );
				setSaving( false );
				setNotice( { type: 'success', msg: __( 'Session types saved.', 'tsb' ) } );
			} )
			.catch( () => {
				setSaving( false );
				setNotice( { type: 'error', msg: __( 'Could not save session types.', 'tsb' ) } );
			} );
	}

	const current = types[ sel ];

	return (
		<div className="tsb-tab-body">
			{ notice && (
				<Notice status={ notice.type } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }
			<div className="tsb-types-admin">
				<aside className="tsb-types-list">
					<VStack spacing={ 1 }>
						{ types.map( ( t, i ) => (
							<div key={ i } className={ 'tsb-type-row' + ( i === sel ? ' is-active' : '' ) + ( t.enabled ? '' : ' is-disabled' ) }>
								<button type="button" className="tsb-type-pick" onClick={ () => setSel( i ) }>
									<span className="tsb-type-pick-label">{ t.label || __( '(untitled)', 'tsb' ) }</span>
									<span className="tsb-type-pick-meta">{ t.slot_minutes } { __( 'min', 'tsb' ) }{ t.enabled ? '' : ' · ' + __( 'off', 'tsb' ) }</span>
								</button>
								<span className="tsb-type-rowbtns">
									<Button size="small" variant="tertiary" icon="arrow-up-alt2" label={ __( 'Move up', 'tsb' ) } disabled={ i === 0 } onClick={ () => move( i, -1 ) } />
									<Button size="small" variant="tertiary" icon="arrow-down-alt2" label={ __( 'Move down', 'tsb' ) } disabled={ i === types.length - 1 } onClick={ () => move( i, 1 ) } />
									<Button size="small" variant="tertiary" icon="admin-page" label={ __( 'Duplicate', 'tsb' ) } onClick={ () => duplicate( i ) } />
									<Button size="small" variant="tertiary" isDestructive icon="trash" label={ __( 'Delete', 'tsb' ) } disabled={ types.length <= 1 } onClick={ () => remove( i ) } />
								</span>
							</div>
						) ) }
					</VStack>
					<Button variant="secondary" icon="plus" onClick={ addType } className="tsb-type-add" __next40pxDefaultSize>
						{ __( 'Add session type', 'tsb' ) }
					</Button>
				</aside>

				<section className="tsb-types-edit">
					{ current && (
						<TypeEditor
							key={ sel }
							type={ current }
							meta={ m }
							adminEmail={ m.adminEmail }
							onChange={ ( patch ) => patchType( sel, patch ) }
						/>
					) }
				</section>
			</div>

			<div className="tsb-savebar">
				{ dirty && <span className="tsb-unsaved">{ __( 'Unsaved changes', 'tsb' ) }</span> }
				<Button variant="primary" isBusy={ saving } disabled={ ! dirty } onClick={ save } __next40pxDefaultSize>
					{ __( 'Save session types', 'tsb' ) }
				</Button>
			</div>
		</div>
	);
}
