// Load webpack-split chunks (the MJML compiler) from the plugin's build/ dir.
// eslint-disable-next-line camelcase
__webpack_public_path__ = tsbAdmin.buildUrl;

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
