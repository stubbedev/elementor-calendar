import { createRoot } from '@wordpress/element';
import './admin.scss';
import Bookings from './Bookings';
import Settings from './Settings';

function App( { view }: { view?: string } ) {
	return view === 'settings' ? <Settings /> : <Bookings />;
}

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'tsb-admin' );
	if ( root ) {
		createRoot( root ).render( <App view={ root.dataset.view } /> );
	}
} );
