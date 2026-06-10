export interface WeekDay {
	open: number;
	use_base: number;
	start: number;
	end: number;
}

export interface Settings {
	slot_minutes: number;
	slot_offset: number;
	slot_gap: number;
	base_start: number;
	base_end: number;
	days_ahead: number;
	lead_hours: number;
	block_holidays: number;
	holiday_countries: string[];
	week: Record<string, WeekDay>;

	ics_attach: number;
	ics_summary: string;
	ics_location: string;
	emails: Record<string, EmailTemplate>;
	reminder_hours: number;

	google_client_id: string;
	google_client_secret: string;
	google_calendar_id: string;

	captcha_mode: string;
	captcha_site: string;
	captcha_secret: string;
	captcha_min_score: number;

	fields: Field[];
	consent_enable: number;
	consent_text: string;
	consent_link_text: string;
	consent_url: string;
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
	slot_offset: number;
	slot_gap: number;
	base_start: number;
	base_end: number;
	days_ahead: number;
	lead_hours: number;
	block_holidays: number;
	holiday_countries: string[];
	week: Record< string, WeekDay >;
	emails: Record< string, EmailTemplate >;
	reminder_hours: number;
	ics_attach: number;
	ics_summary: string;
	ics_location: string;
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
	captchaModes: Record<string, string>;
}

export interface Booking {
	id: number;
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
	reason: string;
}
