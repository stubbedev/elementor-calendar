import apiFetch from '@wordpress/api-fetch';

// WordPress already registers its own root-URL middleware (which handles the
// ?rest_route= form on plain permalinks); we only refresh the nonce and prefix
// every path with our namespace.
apiFetch.use( apiFetch.createNonceMiddleware( tsbAdmin.nonce ) );

interface ApiOpts {
	method?: string;
	data?: unknown;
}

export function api< T = unknown >( path: string, opts: ApiOpts = {} ): Promise< T > {
	return apiFetch< T >( { ...opts, path: 'tsb/v1/' + path } );
}

export function qs( obj: Record< string, string | number | undefined > ): string {
	return Object.keys( obj )
		.filter( ( k ) => obj[ k ] !== '' && obj[ k ] != null )
		.map( ( k ) => encodeURIComponent( k ) + '=' + encodeURIComponent( String( obj[ k ] ) ) )
		.join( '&' );
}
