import {
	Button,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';
import TypeEditor from './TypeEditor';
import type { SessionType, TypesMeta } from './types';

export default function TypeManager() {
	const [ types, setTypes ] = useState< SessionType[] | null >( null );
	const [ meta, setMeta ] = useState< TypesMeta | null >( null );
	const [ editing, setEditing ] = useState< number | null >( null );
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
				slot_minutes: base.slot_minutes, slot_gap: base.slot_gap,
				base_start: base.base_start, base_end: base.base_end,
				week: JSON.parse( JSON.stringify( base.week ) ),
			}
			: {
				slot_minutes: 30, slot_gap: 0, base_start: 9, base_end: 17,
				week: Object.fromEntries( [ 1, 2, 3, 4, 5, 6, 7 ].map( ( d ) => [ d, { open: d <= 5 ? 1 : 0, use_base: 1, start: 9, end: 17 } ] ) ),
			};
		return {
			id: '', label: __( 'New session', 'tsb' ), enabled: 1, order: ( types?.length ?? 0 ), description: '',
			...avail,
			emails: JSON.parse( JSON.stringify( m.emailDefaults ) ),
			reminder_hours: 24, meet_enabled: 0,
		};
	}

	function addType() {
		setTypes( ( prev ) => {
			const next = [ ...( prev || [] ), blankFrom( ( prev || [] )[ 0 ] ) ];
			setEditing( next.length - 1 ); // open the new type straight away
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
			return next;
		} );
	}

	function remove( idx: number ) {
		setTypes( ( prev ) => {
			if ( ! prev || prev.length <= 1 ) { return prev; }
			return prev.filter( ( _, i ) => i !== idx );
		} );
	}

	function move( idx: number, dir: -1 | 1 ) {
		setTypes( ( prev ) => {
			if ( ! prev ) { return prev; }
			const j = idx + dir;
			if ( j < 0 || j >= prev.length ) { return prev; }
			const next = [ ...prev ];
			[ next[ idx ], next[ j ] ] = [ next[ j ], next[ idx ] ];
			return next;
		} );
	}

	function save() {
		setSaving( true );
		api< { types: SessionType[] } >( 'types', { method: 'POST', data: { types } } )
			.then( ( r ) => {
				setTypes( r.types );
				setSaved( JSON.stringify( r.types ) );
				setEditing( ( e ) => ( e === null ? null : Math.max( 0, Math.min( e, r.types.length - 1 ) ) ) );
				setSaving( false );
				setNotice( { type: 'success', msg: __( 'Session types saved.', 'tsb' ) } );
			} )
			.catch( () => {
				setSaving( false );
				setNotice( { type: 'error', msg: __( 'Could not save session types.', 'tsb' ) } );
			} );
	}

	const saveBar = (
		<div className="tsb-savebar">
			{ dirty && <span className="tsb-unsaved">{ __( 'Unsaved changes', 'tsb' ) }</span> }
			<Button variant="primary" isBusy={ saving } disabled={ ! dirty } onClick={ save } __next40pxDefaultSize>
				{ __( 'Save session types', 'tsb' ) }
			</Button>
		</div>
	);

	const editingType = editing !== null ? types[ editing ] : undefined;

	return (
		<div className="tsb-tab-body">
			{ notice && (
				<Notice status={ notice.type } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

			{ editing === null || ! editingType ? (
				<>
					<table className="wp-list-table widefat striped tsb-table tsb-types-table">
						<thead>
							<tr>
								<th>{ __( 'Session type', 'tsb' ) }</th>
								<th className="tsb-col-len">{ __( 'Length', 'tsb' ) }</th>
								<th className="tsb-col-status">{ __( 'Status', 'tsb' ) }</th>
								<th className="tsb-col-actions" />
							</tr>
						</thead>
						<tbody>
							{ types.map( ( t, i ) => (
								<tr key={ i } className={ t.enabled ? '' : 'is-cancelled' }>
									<td>
										<button type="button" className="tsb-type-name-link" onClick={ () => setEditing( i ) }>
											<strong>{ t.label || __( '(untitled)', 'tsb' ) }</strong>
										</button>
										{ t.description && <div className="tsb-type-desc-sm">{ t.description }</div> }
									</td>
									<td className="tsb-col-len">{ t.slot_minutes } { __( 'min', 'tsb' ) }</td>
									<td className="tsb-col-status">
										<span className={ 'tsb-status is-' + ( t.enabled ? 'confirmed' : 'cancelled' ) }>
											{ t.enabled ? __( 'Enabled', 'tsb' ) : __( 'Disabled', 'tsb' ) }
										</span>
									</td>
									<td className="tsb-col-actions">
										<div className="tsb-type-actions">
											<Button size="small" variant="tertiary" icon="arrow-up-alt2" label={ __( 'Move up', 'tsb' ) } disabled={ i === 0 } onClick={ () => move( i, -1 ) } />
											<Button size="small" variant="tertiary" icon="arrow-down-alt2" label={ __( 'Move down', 'tsb' ) } disabled={ i === types.length - 1 } onClick={ () => move( i, 1 ) } />
											<span className="tsb-type-actions-sep" aria-hidden="true" />
											<Button size="small" variant="secondary" onClick={ () => setEditing( i ) }>{ __( 'Edit', 'tsb' ) }</Button>
											<Button size="small" variant="tertiary" icon="admin-page" label={ __( 'Duplicate', 'tsb' ) } onClick={ () => duplicate( i ) } />
											<Button size="small" variant="tertiary" isDestructive icon="trash" label={ __( 'Delete', 'tsb' ) } disabled={ types.length <= 1 } onClick={ () => remove( i ) } />
										</div>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
					<Button variant="secondary" icon="plus" onClick={ addType } className="tsb-type-add" __next40pxDefaultSize>
						{ __( 'Add session type', 'tsb' ) }
					</Button>
				</>
			) : (
				<>
					<div className="tsb-type-editbar">
						<Button variant="tertiary" icon="arrow-left-alt2" onClick={ () => setEditing( null ) }>
							{ __( 'All session types', 'tsb' ) }
						</Button>
						<span className="tsb-type-editbar-name">{ editingType.label || __( '(untitled)', 'tsb' ) }</span>
					</div>
					<TypeEditor
						key={ editing }
						type={ editingType }
						meta={ m }
						adminEmail={ m.adminEmail }
						onChange={ ( patch ) => patchType( editing, patch ) }
					/>
				</>
			) }

			{ saveBar }
		</div>
	);
}
