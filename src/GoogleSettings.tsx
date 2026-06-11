import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Spinner,
	Notice,
	TextControl,
	ExternalLink,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';

interface Status {
	configured: boolean;
	connected: boolean;
	email: string;
	authUrl: string;
	redirectUri: string;
}

interface Props {
	clientId: string;
	clientSecret: string;
	calendarId: string;
	dirty: boolean; // unsaved credential changes in the global form
	onChange: ( key: 'google_client_id' | 'google_client_secret' | 'google_calendar_id', value: string ) => void;
}

export default function GoogleSettings( { clientId, clientSecret, calendarId, dirty, onChange }: Props ) {
	const [ status, setStatus ] = useState< Status | null >( null );
	const [ busy, setBusy ] = useState( false );

	function load() {
		api< Status >( 'google' ).then( setStatus ).catch( () => setStatus( null ) );
	}
	useEffect( load, [] );

	// Surface the OAuth redirect result (?tsb_google=connected|state|denied|…).
	const params = new URLSearchParams( window.location.search );
	const result = params.get( 'tsb_google' );

	function disconnect() {
		setBusy( true );
		api( 'google/disconnect', { method: 'POST' } )
			.then( load )
			.finally( () => setBusy( false ) );
	}

	return (
		<VStack spacing={ 5 }>
			{ result === 'connected' && (
				<Notice status="success" isDismissible={ false }>{ __( 'Google account connected.', 'tsb' ) }</Notice>
			) }
			{ result && result !== 'connected' && (
				<Notice status="error" isDismissible={ false }>{ __( 'Google connection failed. Please try again.', 'tsb' ) }</Notice>
			) }

			<Card className="tsb-card tsb-card-narrow">
				<CardHeader>{ __( 'Google Calendar & Meet', 'tsb' ) }</CardHeader>
				<CardBody>
					<VStack spacing={ 4 }>
						<p className="tsb-help">
							{ __( 'Connect a Google account so session types with “Video meeting” enabled create a Calendar event with a Meet link per booking.', 'tsb' ) }
						</p>

						<ol className="tsb-help tsb-google-steps">
							<li>{ __( 'In Google Cloud Console, create an OAuth 2.0 Client ID of type “Web application” and enable the Google Calendar API.', 'tsb' ) }</li>
							<li>
								{ __( 'Add this exact Authorized redirect URI:', 'tsb' ) }
								<code className="tsb-redirect-uri">{ status?.redirectUri || '…' }</code>
							</li>
							<li>
								<strong>{ __( 'On the OAuth consent screen, click “Publish app” (set it to Production).', 'tsb' ) }</strong>
								{ ' ' }
								{ __( 'Left in “Testing”, the connection expires every 7 days and you have to reconnect. A free Gmail account is fine — no Workspace or payment needed. You may see an “unverified app” warning at connect; click through it once.', 'tsb' ) }
							</li>
							<li>{ __( 'Paste the client ID and secret below, save, then click Connect.', 'tsb' ) }</li>
						</ol>

						<TextControl
							label={ __( 'Client ID', 'tsb' ) }
							value={ clientId }
							onChange={ ( v ) => onChange( 'google_client_id', v ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __( 'Client secret', 'tsb' ) }
							type="password"
							value={ clientSecret }
							onChange={ ( v ) => onChange( 'google_client_secret', v ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __( 'Calendar ID', 'tsb' ) }
							value={ calendarId }
							placeholder="primary"
							onChange={ ( v ) => onChange( 'google_calendar_id', v ) }
							help={ __( 'Usually “primary”. Use a specific calendar address to book into a shared calendar.', 'tsb' ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						{ ! status && <Spinner /> }

						{ status && (
							<div className="tsb-google-conn">
								{ status.connected ? (
									<>
										<Notice status="success" isDismissible={ false }>
											{ status.email
												? sprintfEmail( status.email )
												: __( 'Connected to Google.', 'tsb' ) }
										</Notice>
										<Button variant="secondary" isDestructive isBusy={ busy } onClick={ disconnect } __next40pxDefaultSize>
											{ __( 'Disconnect', 'tsb' ) }
										</Button>
									</>
								) : (
									<>
										{ ! status.configured && (
											<p className="tsb-help">{ __( 'Enter the client ID and secret above and save settings first.', 'tsb' ) }</p>
										) }
										{ status.configured && dirty && (
											<Notice status="warning" isDismissible={ false }>
												{ __( 'Save your credential changes before connecting.', 'tsb' ) }
											</Notice>
										) }
										{ status.configured && ! dirty && status.authUrl && (
											<Button variant="primary" href={ status.authUrl } __next40pxDefaultSize>
												{ __( 'Connect Google account', 'tsb' ) }
											</Button>
										) }
									</>
								) }
							</div>
						) }

						<ExternalLink href="https://console.cloud.google.com/apis/credentials">
							{ __( 'Open Google Cloud Console', 'tsb' ) }
						</ExternalLink>
					</VStack>
				</CardBody>
			</Card>
		</VStack>
	);
}

function sprintfEmail( email: string ) {
	// translators: %s is the connected Google account email.
	return __( 'Connected as', 'tsb' ) + ' ' + email;
}
