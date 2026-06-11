export interface WeekDay {
	open: number;
	use_base: number;
	start: number;
	end: number;
}

export interface Settings {
	// Global scheduling rules (apply to every session type).
	days_ahead: number;
	lead_hours: number;
	block_holidays: number;
	holiday_countries: string[];

	// Admin notification template (per-event customer emails live on each type).
	emails: Record<string, EmailTemplate>;

	google_client_id: string;
	google_client_secret: string;
	google_calendar_id: string;

	fields: Field[];
}

export interface Field {
	name: string;
	label: string;
	type: string;
	enabled: number;
	required: number;
}

export interface EmailTemplate {
	enabled: number;
	subject: string;
	mjml: string;
	html: string;
	to?: string;
}

export interface SessionType {
	id: string;
	label: string;
	enabled: number;
	order: number;
	description: string;
	slot_minutes: number;
	slot_gap: number;
	base_start: number;
	base_end: number;
	week: Record< string, WeekDay >;
	emails: Record< string, EmailTemplate >;
	reminder_hours: number;
	meet_enabled: number;
}

export interface TypesMeta {
	weekdays: Record< string, string >;
	countries: Record< string, string >;
	emailEvents: Record< string, string >;
	tokensByEvent: Record< string, string[] >;
	tokenLabels: Record< string, string >;
	sampleVars: Record< string, string >;
	emailDefaults: Record< string, EmailTemplate >;
	adminEmail: string;
	googleReady: boolean;
}

export interface Meta {
	weekdays: Record<string, string>;
	countries: Record<string, string>;
	fieldTypes: Record<string, string>;
	emailEvents: Record<string, string>;
	emailTokens: string[];
	tokensByEvent: Record<string, string[]>;
	tokenLabels: Record<string, string>;
	sampleVars: Record<string, string>;
	emailDefaults: Record<string, EmailTemplate>;
	adminEmail: string;
}

export interface Booking {
	id: number;
	type_id: string;
	type_label: string;
	slot_date: string;
	slot_time: string;
	name: string;
	email: string;
	phone: string;
	message: string;
	status: string;
}

export interface Slot {
	time: string;
	available: boolean;
	reason: string;
}

export interface Block {
	id: number;
	block_date: string;
	block_time: string;
	block_end: string;
	reason: string;
}
