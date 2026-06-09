import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode, MouseEvent as ReactMouseEvent } from 'react';

interface Props {
	tokens: string[];
	labels: Record< string, string >;
	// Custom insert (e.g. CodeMirror). When provided, every right-click inside
	// this wrapper routes through it. Otherwise the menu splices `{{token}}` into
	// the right-clicked <input>/<textarea> at its caret.
	insert?: ( text: string ) => void;
	children: ReactNode;
}

type Field = HTMLInputElement | HTMLTextAreaElement;

// Set a controlled React input's value through the native setter so React's
// onChange still fires (assigning .value directly is swallowed by React).
function spliceField( el: Field, start: number, end: number, text: string ) {
	const proto = el instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
	const setter = Object.getOwnPropertyDescriptor( proto, 'value' )?.set;
	const next = el.value.slice( 0, start ) + text + el.value.slice( end );
	setter?.call( el, next );
	el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	const pos = start + text.length;
	el.focus();
	el.setSelectionRange( pos, pos );
}

export default function VarMenu( { tokens, labels, insert, children }: Props ) {
	const [ menu, setMenu ] = useState< { x: number; y: number } | null >( null );
	const fieldRef = useRef< { el: Field; start: number; end: number } | null >( null );
	const menuRef = useRef< HTMLDivElement >( null );

	function onContextMenu( e: ReactMouseEvent ) {
		let field: Field | null = null;
		if ( ! insert ) {
			field = ( e.target as HTMLElement ).closest( 'input, textarea' ) as Field | null;
			if ( ! field ) {
				return; // nothing to target → leave the native menu alone
			}
		}
		e.preventDefault();
		fieldRef.current = field
			? { el: field, start: field.selectionStart ?? field.value.length, end: field.selectionEnd ?? field.value.length }
			: null;
		// Clamp so the menu stays inside the viewport.
		const x = Math.min( e.clientX, window.innerWidth - 200 );
		const y = Math.min( e.clientY, window.innerHeight - Math.min( tokens.length * 30 + 16, 320 ) );
		setMenu( { x, y } );
	}

	function pick( token: string ) {
		const snippet = '{{' + token + '}}';
		const f = fieldRef.current;
		if ( f ) {
			spliceField( f.el, f.start, f.end, snippet );
		} else if ( insert ) {
			insert( snippet );
		}
		setMenu( null );
	}

	useEffect( () => {
		if ( ! menu ) {
			return;
		}
		const close = ( ev: Event ) => {
			if ( menuRef.current && ev.target instanceof Node && menuRef.current.contains( ev.target ) ) {
				return;
			}
			setMenu( null );
		};
		const onKey = ( ev: KeyboardEvent ) => ev.key === 'Escape' && setMenu( null );
		document.addEventListener( 'pointerdown', close );
		document.addEventListener( 'keydown', onKey );
		window.addEventListener( 'scroll', () => setMenu( null ), true );
		return () => {
			document.removeEventListener( 'pointerdown', close );
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ menu ] );

	return (
		<div className="tsb-varmenu-wrap" onContextMenu={ onContextMenu }>
			{ children }
			{ menu && (
				<div ref={ menuRef } className="tsb-varmenu" style={ { left: menu.x, top: menu.y } } role="menu">
					<div className="tsb-varmenu-head">{ __( 'Insert variable', 'tsb' ) }</div>
					{ tokens.map( ( tk ) => (
						<button key={ tk } type="button" className="tsb-varmenu-item" role="menuitem" onClick={ () => pick( tk ) }>
							<code>{ '{{' + tk + '}}' }</code>
							{ labels[ tk ] && <span className="tsb-varmenu-label">{ labels[ tk ] }</span> }
						</button>
					) ) }
				</div>
			) }
		</div>
	);
}
