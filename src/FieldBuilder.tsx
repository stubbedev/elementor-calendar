import { Button, TextControl, SelectControl, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { Field } from './types';

const RESERVED = [ 'name', 'email', 'consent', 'date', 'time', 'stamp', 'captcha_token', 'tsb_hp', 'action', 'nonce', 'lang' ];

function slug( s: string ) {
	return s
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '_' )
		.replace( /^_+|_+$/g, '' )
		.slice( 0, 40 );
}

interface Props {
	fields: Field[];
	types: Record< string, string >;
	onChange: ( fields: Field[] ) => void;
}

export default function FieldBuilder( { fields, types, onChange }: Props ) {
	const [ drag, setDrag ] = useState< number | null >( null );

	function update( i: number, patch: Partial< Field > ) {
		onChange( fields.map( ( f, idx ) => ( idx === i ? { ...f, ...patch } : f ) ) );
	}
	function move( from: number, to: number ) {
		const a = [ ...fields ];
		const [ x ] = a.splice( from, 1 );
		a.splice( to, 0, x );
		onChange( a );
	}
	function add() {
		let n = fields.length + 1;
		let name = 'field_' + n;
		const taken = new Set( [ ...fields.map( ( f ) => f.name ), ...RESERVED ] );
		while ( taken.has( name ) ) {
			name = 'field_' + ++n;
		}
		onChange( [ ...fields, { name, label: '', type: 'text', enabled: 1, required: 0 } ] );
	}
	function del( i: number ) {
		onChange( fields.filter( ( _, idx ) => idx !== i ) );
	}

	const typeOptions = Object.keys( types ).map( ( k ) => ( { label: types[ k ], value: k } ) );

	return (
		<div className="tsb-fields">
			<div className="tsb-fieldrow tsb-fieldrow-head">
				<span />
				<span>{ __( 'Label', 'tsb' ) }</span>
				<span>{ __( 'Name', 'tsb' ) }</span>
				<span>{ __( 'Type', 'tsb' ) }</span>
				<span>{ __( 'Shown', 'tsb' ) }</span>
				<span>{ __( 'Required', 'tsb' ) }</span>
				<span />
			</div>

			{ fields.length === 0 && (
				<p className="tsb-help">{ __( 'No fields yet. Name and email are always shown.', 'tsb' ) }</p>
			) }

			{ fields.map( ( f, i ) => (
				<div
					key={ i }
					className={ 'tsb-fieldrow' + ( drag === i ? ' is-dragging' : '' ) }
					draggable
					onDragStart={ () => setDrag( i ) }
					onDragOver={ ( e ) => e.preventDefault() }
					onDrop={ () => {
						if ( drag !== null && drag !== i ) {
							move( drag, i );
						}
						setDrag( null );
					} }
					onDragEnd={ () => setDrag( null ) }
				>
					<span className="tsb-drag" title={ __( 'Drag to reorder', 'tsb' ) } aria-hidden="true">⠿</span>
					<TextControl
						value={ f.label }
						placeholder={ __( 'Label', 'tsb' ) }
						onChange={ ( v ) => {
							const patch: Partial< Field > = { label: v };
							if ( ! f.name || f.name === slug( f.label ) ) {
								patch.name = slug( v );
							}
							update( i, patch );
						} }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						value={ f.name }
						placeholder="name"
						onChange={ ( v ) => update( i, { name: slug( v ) } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<SelectControl
						value={ f.type }
						options={ typeOptions }
						onChange={ ( v ) => update( i, { type: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<ToggleControl
						label={ ( f.label || f.name ) + ' — ' + __( 'Shown', 'tsb' ) }
						className="tsb-toggle-bare"
						checked={ !! f.enabled }
						onChange={ ( v ) => update( i, { enabled: v ? 1 : 0 } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ ( f.label || f.name ) + ' — ' + __( 'Required', 'tsb' ) }
						className="tsb-toggle-bare"
						checked={ !! f.required }
						onChange={ ( v ) => update( i, { required: v ? 1 : 0 } ) }
						__nextHasNoMarginBottom
					/>
					<Button
						className="tsb-field-del"
						variant="tertiary"
						size="small"
						isDestructive
						label={ __( 'Delete', 'tsb' ) }
						onClick={ () => del( i ) }
					>
						×
					</Button>
				</div>
			) ) }

			<div className="tsb-fields-add">
				<Button variant="secondary" onClick={ add } __next40pxDefaultSize>
					{ __( '+ Add field', 'tsb' ) }
				</Button>
			</div>
		</div>
	);
}
