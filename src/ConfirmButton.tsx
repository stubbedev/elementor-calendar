import { Button } from '@wordpress/components';
import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';

interface Props {
	onConfirm: () => void;
	children: ReactNode;
	confirmLabel?: string;
	variant?: 'primary' | 'secondary' | 'tertiary' | 'link';
	size?: 'small' | 'compact' | 'default';
	isDestructive?: boolean;
	className?: string;
	disabled?: boolean;
}

/**
 * Click-twice confirmation: first click arms the button (label → "Sure?"), a
 * second click within a few seconds confirms. No native window.confirm (which
 * some browsers/extensions block).
 */
export default function ConfirmButton( {
	onConfirm,
	children,
	confirmLabel,
	variant,
	size,
	isDestructive,
	className,
	disabled,
}: Props ) {
	const [ armed, setArmed ] = useState( false );
	const timer = useRef< ReturnType< typeof setTimeout > | undefined >( undefined );

	function disarm() {
		setArmed( false );
		if ( timer.current ) {
			clearTimeout( timer.current );
		}
	}

	return (
		<Button
			className={ ( className || '' ) + ( armed ? ' is-armed' : '' ) }
			variant={ armed ? 'primary' : variant }
			size={ size }
			isDestructive={ isDestructive }
			disabled={ disabled }
			onClick={ () => {
				if ( armed ) {
					disarm();
					onConfirm();
				} else {
					setArmed( true );
					timer.current = setTimeout( () => setArmed( false ), 5000 );
				}
			} }
		>
			{ armed ? ( confirmLabel || __( 'Sure?', 'tsb' ) ) : children }
		</Button>
	);
}
