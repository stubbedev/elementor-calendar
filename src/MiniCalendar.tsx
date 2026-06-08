import { Button } from '@wordpress/components';

export interface DayStatus {
	status: 'free' | 'partial' | 'full' | 'wholeday' | 'closed';
	free?: number;
	open?: number;
}

interface Props {
	view: { y: number; m: number };
	selected: string;
	days: Record< string, DayStatus >;
	onSelect: ( date: string ) => void;
	onNav: ( delta: number ) => void;
}

function pad( n: number ) {
	return ( n < 10 ? '0' : '' ) + n;
}
function ymd( y: number, m: number, d: number ) {
	return y + '-' + pad( m + 1 ) + '-' + pad( d );
}

export default function MiniCalendar( { view, selected, days, onSelect, onNav }: Props ) {
	const { y, m } = view;
	const title = new Date( y, m, 1 ).toLocaleDateString( undefined, { month: 'long', year: 'numeric' } );

	// Monday-first weekday short names (2024-01-01 is a Monday).
	const weekdays = [];
	for ( let i = 0; i < 7; i++ ) {
		weekdays.push( new Date( 2024, 0, 1 + i ).toLocaleDateString( undefined, { weekday: 'short' } ) );
	}

	const first = new Date( y, m, 1 );
	const offset = ( first.getDay() + 6 ) % 7; // Monday-first
	const dim = new Date( y, m + 1, 0 ).getDate();

	const cells = [];
	for ( let i = 0; i < offset; i++ ) {
		cells.push( <span key={ 'b' + i } className="tsb-mini-day is-blank" /> );
	}
	for ( let d = 1; d <= dim; d++ ) {
		const key = ymd( y, m, d );
		const st = ( days[ key ] && days[ key ].status ) || 'free';
		const cls = 'tsb-mini-day is-' + st + ( key === selected ? ' is-selected' : '' );
		cells.push(
			<button key={ key } type="button" className={ cls } onClick={ () => onSelect( key ) }>
				{ d }
			</button>
		);
	}

	return (
		<div className="tsb-mini">
			<div className="tsb-mini-head">
				<Button className="tsb-mini-nav" variant="tertiary" onClick={ () => onNav( -1 ) } label="‹">‹</Button>
				<span className="tsb-mini-title">{ title }</span>
				<Button className="tsb-mini-nav" variant="tertiary" onClick={ () => onNav( 1 ) } label="›">›</Button>
			</div>
			<div className="tsb-mini-weekdays">
				{ weekdays.map( ( w, i ) => <span key={ i }>{ w }</span> ) }
			</div>
			<div className="tsb-mini-grid">{ cells }</div>
		</div>
	);
}
